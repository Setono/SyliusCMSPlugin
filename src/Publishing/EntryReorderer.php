<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Event\EntrySettingsChanged;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\UnknownContentTypeException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Saves the positions of entries of a content type ordered by position in one transaction, as EntrySettingsSaver saves
 * the other on-save settings. See "Entry lists" in docs/spec/admin.md and "What takes effect on save" in
 * docs/spec/publishing.md
 */
final readonly class EntryReorderer implements EntryReordererInterface
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
        private EditRecorder $editRecorder,
        private ContentTypeRegistryInterface $contentTypes,
    ) {
    }

    public function reorder(string $contentType, array $uuids): void
    {
        try {
            $metadata = $this->contentTypes->get($contentType);
        } catch (UnknownContentTypeException $e) {
            throw new InvalidEntrySettingsException($e->getMessage(), previous: $e);
        }

        if (!$metadata->order->byPosition) {
            throw new InvalidEntrySettingsException(sprintf('The content type "%s" is not ordered by position', $contentType));
        }

        $manager = EntityManagers::of($this->managerRegistry, $metadata->modelClass);
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));

        /** @var list<string> $changed */
        $changed = [];
        $manager->wrapInTransaction(function () use ($manager, $metadata, $uuids, $now, &$changed): void {
            /** @var list<array{uuid: string, position: int|string|null}> $rows */
            $rows = $manager->createQueryBuilder()
                ->select('o.uuid AS uuid', 'o.position AS position')
                ->from($metadata->modelClass, 'o')
                ->orderBy('o.id', 'ASC')
                ->getQuery()
                ->getScalarResult()
            ;

            $current = [];
            foreach ($rows as $row) {
                $current[$row['uuid']] = null === $row['position'] ? null : (int) $row['position'];
            }

            $positions = EntryPositions::reorder($current, $uuids);
            if ([] === $positions) {
                return;
            }

            /** @var list<EntryInterface> $entries */
            $entries = $manager->getRepository($metadata->modelClass)->findBy(['uuid' => array_keys($positions)]);
            foreach ($entries as $entry) {
                $entry->setPosition($positions[$entry->getUuid()]);
                $entry->setChangedAt($now);
                $this->editRecorder->record($entry);
                $changed[] = $entry->getUuid();
            }
        });

        // After the transaction commits
        foreach ($changed as $uuid) {
            $this->eventDispatcher->dispatch(new EntrySettingsChanged($uuid, [EntrySettingsChanged::POSITION]));
        }
    }
}
