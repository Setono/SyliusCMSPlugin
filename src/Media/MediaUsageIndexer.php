<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Setono\SyliusCMSPlugin\EntryFormat\InvalidEntryFormatException;
use Setono\SyliusCMSPlugin\Field\MediaItemReferences;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EditorPresetInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\MediaUsageInterface;
use Setono\SyliusCMSPlugin\Preset\EditorPresetContent;
use Setono\SyliusCMSPlugin\Preset\InvalidPresetException;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\FoundReference;
use Setono\SyliusCMSPlugin\Tree\InvalidStoredTreeException;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * Works out how an entry's rows of the media usage index change when the entry is written: which media items its
 * published content uses and which its draft uses, in fields and in rich text, as the field type handlers' references()
 * list them with the type media_item, against the rows the index has. Revisions aren't read. And the same for an editor
 * preset, in its blocks and a starting point's layout. Content that can't be read leaves the rows as they are. See
 * "Usage index" in docs/spec/media.md
 *
 * @internal
 */
final readonly class MediaUsageIndexer
{
    /**
     * @param FactoryInterface<MediaUsageInterface> $usageFactory
     */
    public function __construct(
        private WorkingCopyProviderInterface $workingCopies,
        private ReferenceFinder $referenceFinder,
        private ContentTypeRegistryInterface $contentTypes,
        private FactoryInterface $usageFactory,
    ) {
    }

    /**
     * @param DraftInterface|null $draft the entry's draft as it's being written, or null when it has none
     * @param iterable<MediaUsageInterface> $rows the entry's rows of the index as they are
     */
    public function index(EntryInterface $entry, ?DraftInterface $draft, iterable $rows): MediaUsageChanges
    {
        try {
            $uses = $this->uses($entry, $draft);
        } catch (InvalidStoredTreeException|InvalidEntryFormatException) {
            return new MediaUsageChanges();
        }

        $updated = $removed = [];
        foreach ($rows as $row) {
            $use = $uses[$row->getMediaItemUuid()] ?? null;
            unset($uses[$row->getMediaItemUuid()]);

            if (null === $use) {
                $removed[] = $row;

                continue;
            }

            if ($row->isInDraft() !== $use['draft'] || $row->isInPublished() !== $use['published']) {
                $row->setInDraft($use['draft']);
                $row->setInPublished($use['published']);
                $updated[] = $row;
            }
        }

        $created = [];
        foreach ($uses as $uuid => $use) {
            $row = $this->usageFactory->createNew();
            $row->setEntry($entry);
            $row->setMediaItemUuid($uuid);
            $row->setInDraft($use['draft']);
            $row->setInPublished($use['published']);
            $created[] = $row;
        }

        return new MediaUsageChanges($created, $updated, $removed);
    }

    /**
     * @param iterable<MediaUsageInterface> $rows the preset's rows of the index as they are
     */
    public function indexPreset(EditorPresetInterface $preset, iterable $rows): MediaUsageChanges
    {
        try {
            $content = (new EditorPresetContent())->read($preset);
        } catch (InvalidPresetException) {
            return new MediaUsageChanges();
        }

        $uses = array_fill_keys(self::mediaItems($this->referenceFinder->inPreset($content)), true);

        $removed = [];
        foreach ($rows as $row) {
            if (!isset($uses[$row->getMediaItemUuid()])) {
                $removed[] = $row;
            }

            unset($uses[$row->getMediaItemUuid()]);
        }

        $created = [];
        foreach (array_keys($uses) as $uuid) {
            $row = $this->usageFactory->createNew();
            $row->setPreset($preset);
            $row->setMediaItemUuid((string) $uuid);
            $created[] = $row;
        }

        return new MediaUsageChanges($created, [], $removed);
    }

    /**
     * Where the entry uses each media item. The content is read from copies of the entry, so the entry itself, which
     * Doctrine may be writing, is left as it is
     *
     * @return array<string, array{draft: bool, published: bool}> by the media item's UUID
     *
     * @throws InvalidStoredTreeException|InvalidEntryFormatException
     */
    private function uses(EntryInterface $entry, ?DraftInterface $draft): array
    {
        $contentType = $this->contentTypes->getByEntry($entry);
        $workingCopy = $this->workingCopies->getWorkingCopyWithDraft(clone $entry, $draft);

        $published = [];
        if ($entry->isPublished()) {
            $published = null === $draft ? $this->mediaItemsIn($workingCopy, $contentType) : $this->mediaItemsIn($this->workingCopies->getWorkingCopyWithDraft(clone $entry, null), $contentType);
        }

        $uses = [];
        foreach ($this->mediaItemsIn($workingCopy, $contentType) as $uuid) {
            $uses[$uuid] = ['draft' => true, 'published' => false];
        }

        foreach ($published as $uuid) {
            $uses[$uuid] = ['draft' => isset($uses[$uuid]), 'published' => true];
        }

        return $uses;
    }

    /**
     * The UUIDs of the media items an entry's content refers to, in its own fields and its tree, each once, lowercase
     *
     * @return list<string>
     */
    private function mediaItemsIn(EntryInterface $content, ContentTypeMetadata $contentType): array
    {
        return self::mediaItems($this->referenceFinder->inEntry($content, $contentType));
    }

    /**
     * The UUIDs of the media items among the references found, each once, lowercase
     *
     * @param iterable<FoundReference> $found
     *
     * @return list<string>
     */
    private static function mediaItems(iterable $found): array
    {
        $uuids = [];
        foreach (ReferenceFinder::references($found) as $reference) {
            if (MediaItemReferences::TYPE === $reference->type) {
                $uuids[strtolower($reference->id)] = true;
            }
        }

        return array_map(strval(...), array_keys($uuids));
    }
}
