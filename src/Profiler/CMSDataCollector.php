<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Profiler;

use Setono\SyliusCMSPlugin\Context\EntryTitle;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Query\VisibleEntriesQueryInterface;
use Setono\SyliusCMSPlugin\Registry\SlotRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\CandidatePlacement;
use Setono\SyliusCMSPlugin\Rendering\PlacementOutcome;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Sylius\Resource\Model\CodeAwareInterface;
use Sylius\Resource\Model\ResourceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * The CMS panel of the Symfony profiler: each slot rendered on the page, sub-requests' such as taxon.header's included,
 * with the context it resolved and where each key came from, every candidate placement in editor order with whether it
 * showed and, if not, why, its render time, and any block or snippet that threw. Also the blocks that threw outside any
 * slot. Why a snippet isn't visible is read from the visible-entries query when the profiler collects, so a page that
 * isn't profiled doesn't ask. Registered in debug only. See "Profiler panel" in docs/spec/rendering.md
 *
 * @internal
 *
 * @phpstan-type Failure array{entry: string|null, block: string|null, blockType: string|null, placement: string|null, message: string}
 * @phpstan-type Candidate array{placement: string, snippet: string, title: string, outcome: value-of<PlacementOutcome>, reason: string|null}
 * @phpstan-type ContextKey array{key: string, class: string, id: string|null, passed: bool}
 * @phpstan-type Slot array{identifier: string, label: string|null, templateEvent: string|null, subRequest: string|null, context: list<ContextKey>|null, unresolvedKey: string|null, candidates: list<Candidate>|null, shown: int, duration: float|null, failures: list<Failure>}
 */
final class CMSDataCollector extends DataCollector
{
    public const NAME = 'setono_sylius_cms';

    /** @var \WeakMap<SlotTrace, array{reasons: array<string, string>, titles: array<string, string>}> what was read for each slot */
    private \WeakMap $read;

    public function __construct(
        private readonly RenderedSlots $slots,
        private readonly SlotRegistryInterface $slotRegistry,
        private readonly VisibleEntriesQueryInterface $visibleEntries,
        private readonly EntryRepositoryInterface $entries,
    ) {
        $this->read = new \WeakMap();
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data = [
            'slots' => array_map($this->slot(...), $this->slots->all()),
            'failures' => array_map(self::failure(...), $this->slots->failuresOutsideSlots()),
        ];
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
     * @return Slot
     */
    private function slot(SlotTrace $trace): array
    {
        $metadata = $this->slotRegistry->has($trace->identifier) ? $this->slotRegistry->get($trace->identifier) : null;

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
            'candidates' => null === $trace->candidates ? null : $this->candidates($trace, $trace->candidates),
            'shown' => count(array_filter($trace->candidates ?? [], static fn (CandidatePlacement $candidate): bool => $candidate->wins())),
            'duration' => $trace->duration,
            'failures' => array_map(self::failure(...), $trace->failures),
        ];
    }

    /**
     * @param list<CandidatePlacement> $candidates
     *
     * @return list<Candidate>
     */
    private function candidates(SlotTrace $trace, array $candidates): array
    {
        $read = $this->read[$trace] ??= $this->readFor($trace, $candidates);

        return array_map(static fn (CandidatePlacement $candidate): array => [
            'placement' => $candidate->placement->uuid,
            'snippet' => $candidate->placement->snippetUuid,
            'title' => null === $candidate->snippet ? $read['titles'][$candidate->placement->snippetUuid] ?? $candidate->placement->snippetUuid : EntryTitle::of($candidate->snippet),
            'outcome' => $candidate->outcome->value,
            'reason' => PlacementOutcome::SnippetNotVisible === $candidate->outcome ? $read['reasons'][$candidate->placement->snippetUuid] ?? null : null,
        ], $candidates);
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
            foreach ($this->visibleEntries->whyNotVisible('snippet', array_values($notVisible), $trace->renderContext) as $uuid => $reason) {
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
