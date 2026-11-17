<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Profiler;

use Setono\SyliusCMSPlugin\Context\EntryTitle;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Query\NotVisibleReason;
use Setono\SyliusCMSPlugin\Query\VisibleEntriesQueryInterface;
use Setono\SyliusCMSPlugin\Registry\SlotRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\CandidatePlacement;
use Setono\SyliusCMSPlugin\Rendering\PlacementOutcome;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Resource\Model\CodeAwareInterface;
use Sylius\Resource\Model\ResourceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;

/**
 * The CMS panel of the Symfony profiler: each slot rendered on the page, sub-requests' such as taxon.header's included,
 * with the context it resolved and where each key came from, every candidate placement in editor order with whether it
 * showed and, if not, why, its render time, and any block or snippet that threw. Also the blocks that threw outside any
 * slot. Why a snippet isn't visible is read from the visible-entries query, and the placements whose snippet isn't in the
 * channel, which the shop never reads, from the database, when the profiler collects late: after Doctrine's collector,
 * which collects late too, has taken the page's queries, so they aren't counted among them, and a page that isn't
 * profiled doesn't ask. Registered in debug only. See "Profiler panel" in docs/spec/rendering.md
 *
 * @internal
 *
 * @phpstan-type Failure array{entry: string|null, block: string|null, blockType: string|null, placement: string|null, conditionKind: string|null, message: string}
 * @phpstan-type Candidate array{placement: string, snippet: string, title: string, outcome: value-of<PlacementOutcome>, reason: string|null}
 * @phpstan-type ContextKey array{key: string, class: string, id: string|null, passed: bool}
 * @phpstan-type Slot array{identifier: string, label: string|null, templateEvent: string|null, subRequest: string|null, context: list<ContextKey>|null, unresolvedKey: string|null, candidates: list<Candidate>|null, shown: int, duration: float|null, failures: list<Failure>}
 */
final class CMSDataCollector extends DataCollector implements LateDataCollectorInterface
{
    public const NAME = 'setono_sylius_cms';

    /** @var \WeakMap<SlotTrace, array{reasons: array<string, string>, titles: array<string, string>}> what was read for each slot */
    private \WeakMap $read;

    /** @var \WeakMap<SlotTrace, list<PlacementInterface>> each slot's placements whose snippet isn't in the channel */
    private \WeakMap $outsideChannel;

    /** Whether it last collected for the main request, whose profile is saved after its sub-requests' */
    private bool $main = false;

    public function __construct(
        private readonly RenderedSlots $slots,
        private readonly SlotRegistryInterface $slotRegistry,
        private readonly VisibleEntriesQueryInterface $visibleEntries,
        private readonly EntryRepositoryInterface $entries,
        private readonly PlacementRepositoryInterface $placements,
        private readonly ChannelContextInterface $channelContext,
        private readonly RequestStack $requestStack,
    ) {
        $this->read = new \WeakMap();
        $this->outsideChannel = new \WeakMap();
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        // What it reads from the database waits for lateCollect()
        $this->main = $this->requestStack->getMainRequest() === $request;
        $this->data = $this->collected(false);
    }

    /**
     * Reads why snippets aren't visible, the titles of those the slots didn't load and the placements outside the
     * channel, for the main request's profile, which lists its sub-requests' slots too. A sub-request's is saved before
     * it, so reading there would come before Doctrine's collector takes the main request's queries
     */
    public function lateCollect(): void
    {
        if (!$this->main) {
            return;
        }

        $this->readOutsideChannel();
        $this->data = $this->collected(true);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    /**
     * @return list<Slot>
     */
    public function getSlots(): array
    {
        /** @var list<Slot> $slots */
        $slots = $this->data['slots'] ?? [];

        return $slots;
    }

    /**
     * @return list<Failure>
     */
    public function getFailuresOutsideSlots(): array
    {
        /** @var list<Failure> $failures */
        $failures = $this->data['failures'] ?? [];

        return $failures;
    }

    public function getSlotCount(): int
    {
        return count($this->getSlots());
    }

    /**
     * The slots with a placement that shows
     */
    public function getShowingSlotCount(): int
    {
        return count(array_filter($this->getSlots(), static fn (array $slot): bool => $slot['shown'] > 0));
    }

    /**
     * The blocks and snippets that threw, in slots and outside them
     */
    public function getFailureCount(): int
    {
        return array_sum(array_map(static fn (array $slot): int => count($slot['failures']), $this->getSlots())) + count($this->getFailuresOutsideSlots());
    }

    /**
     * @param bool $read whether it reads from the database, which only lateCollect() does
     *
     * @return array{slots: list<Slot>, failures: list<Failure>}
     */
    private function collected(bool $read): array
    {
        return [
            'slots' => array_map(fn (SlotTrace $trace): array => $this->slot($trace, $read), $this->slots->all()),
            'failures' => array_map(self::failure(...), $this->slots->failuresOutsideSlots()),
        ];
    }

    /**
     * @return Slot
     */
    private function slot(SlotTrace $trace, bool $read): array
    {
        $metadata = $this->slotRegistry->find($trace->identifier);

        return [
            'identifier' => $trace->identifier,
            'label' => $metadata?->label,
            'templateEvent' => $metadata?->templateEvent,
            'subRequest' => $trace->subRequest,
            'context' => null === $trace->context ? null : array_map(
                static fn (string $key, array $resolved): array => ['key' => $key, 'class' => $resolved['object']::class, 'id' => self::idOf($resolved['object']), 'passed' => $resolved['passed']],
                array_keys($trace->context),
                array_values($trace->context),
            ),
            'unresolvedKey' => $trace->unresolvedKey,
            'candidates' => $this->candidates($trace, $read),
            'shown' => count(array_filter($trace->candidates ?? [], static fn (CandidatePlacement $candidate): bool => $candidate->wins())),
            'duration' => $trace->duration,
            'failures' => array_map(self::failure(...), $trace->failures),
        ];
    }

    /**
     * Its candidates in the channel, with its placements whose snippet isn't in the channel among them, in editor order.
     * Null when it picked none and has none outside the channel, or when it stopped before it picked, as its candidates
     * in the channel are unknown then
     *
     * @return list<Candidate>|null
     */
    private function candidates(SlotTrace $trace, bool $read): ?array
    {
        $outsideChannel = $this->outsideChannel[$trace] ?? [];
        if (null === $trace->candidates && [] === $outsideChannel) {
            return null;
        }

        $candidates = $trace->candidates ?? [];
        $found = $read ? ($this->read[$trace] ??= $this->readFor($trace, $candidates)) : ['reasons' => [], 'titles' => []];

        $listed = [];
        foreach ($candidates as $candidate) {
            $listed[] = [$candidate->placement->position, [
                'placement' => $candidate->placement->uuid,
                'snippet' => $candidate->placement->snippetUuid,
                'title' => null === $candidate->snippet ? $found['titles'][$candidate->placement->snippetUuid] ?? $candidate->placement->snippetUuid : EntryTitle::of($candidate->snippet),
                'outcome' => $candidate->outcome->value,
                'reason' => PlacementOutcome::SnippetNotVisible === $candidate->outcome ? $found['reasons'][$candidate->placement->snippetUuid] ?? null : null,
            ]];
        }

        foreach ($outsideChannel as $placement) {
            $listed[] = [$placement->getPosition(), [
                'placement' => $placement->getUuid(),
                'snippet' => $placement->getSnippet()->getUuid(),
                'title' => EntryTitle::of($placement->getSnippet()),
                'outcome' => PlacementOutcome::SnippetNotVisible->value,
                'reason' => NotVisibleReason::NotInChannel->value,
            ]];
        }

        // By position, the slot's own order; at the same position, those in the channel first, as it read them
        usort($listed, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return array_column($listed, 1);
    }

    /**
     * The placements whose snippet isn't in the channel, of every slot that picked or had no placements in the channel,
     * with one query for all of them. A slot that stopped before it picked lists no candidates, so it isn't asked
     */
    private function readOutsideChannel(): void
    {
        $unread = [];
        foreach ($this->slots->all() as $trace) {
            if (!isset($this->outsideChannel[$trace]) && null === $trace->unresolvedKey) {
                $this->outsideChannel[$trace] = [];
                $unread[$trace->identifier][] = $trace;
            }
        }

        if ([] === $unread) {
            return;
        }

        try {
            $channelCode = (string) $this->channelContext->getChannel()->getCode();
        } catch (ChannelNotFoundException) {
            return;
        }

        foreach ($this->placements->findOutsideChannel(array_keys($unread), $channelCode) as $placement) {
            foreach ($unread[$placement->getSlot()] ?? [] as $trace) {
                $this->outsideChannel[$trace] = [...$this->outsideChannel[$trace] ?? [], $placement];
            }
        }
    }

    /**
     * Why the snippets that aren't visible aren't, and the titles of those the slot didn't load, with one query each
     *
     * @param list<CandidatePlacement> $candidates
     *
     * @return array{reasons: array<string, string>, titles: array<string, string>}
     */
    private function readFor(SlotTrace $trace, array $candidates): array
    {
        $notLoaded = $notVisible = [];
        foreach ($candidates as $candidate) {
            if (null === $candidate->snippet) {
                $notLoaded[$candidate->placement->snippetUuid] = $candidate->placement->snippetUuid;
            }

            if (PlacementOutcome::SnippetNotVisible === $candidate->outcome) {
                $notVisible[$candidate->placement->snippetUuid] = $candidate->placement->snippetUuid;
            }
        }

        $reasons = [];
        if ([] !== $notVisible && null !== $trace->renderContext) {
            foreach ($this->visibleEntries->whyNotVisible(SnippetInterface::CONTENT_TYPE, array_values($notVisible), $trace->renderContext) as $uuid => $reason) {
                $reasons[$uuid] = $reason->value;
            }
        }

        $titles = [];
        if ([] !== $notLoaded) {
            foreach ($this->entries->findByUuids(array_values($notLoaded)) as $entry) {
                $titles[$entry->getUuid()] = EntryTitle::of($entry);
            }
        }

        return ['reasons' => $reasons, 'titles' => $titles];
    }

    /**
     * @return Failure
     */
    private static function failure(RecordedFailure $failure): array
    {
        return [
            'entry' => $failure->entry,
            'block' => $failure->block,
            'blockType' => $failure->blockType,
            'placement' => $failure->placement,
            'conditionKind' => $failure->conditionKind,
            'message' => $failure->message,
        ];
    }

    /**
     * How the panel tells a context object apart: its code, an entry's UUID, or its id
     */
    private static function idOf(object $object): ?string
    {
        return match (true) {
            $object instanceof CodeAwareInterface && null !== $object->getCode() => $object->getCode(),
            $object instanceof EntryInterface => $object->getUuid(),
            $object instanceof ResourceInterface && is_scalar($object->getId()) => (string) $object->getId(),
            default => null,
        };
    }
}
