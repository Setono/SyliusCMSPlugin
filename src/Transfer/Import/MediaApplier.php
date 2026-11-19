<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Event\AfterCommitDispatcher;
use Setono\SyliusCMSPlugin\Event\AfterCommitMessageBus;
use Setono\SyliusCMSPlugin\Event\MediaItemReplaced;
use Setono\SyliusCMSPlugin\Event\MediaItemSaved;
use Setono\SyliusCMSPlugin\Media\DeleteReplacedFile;
use Setono\SyliusCMSPlugin\Media\GracePeriod;
use Setono\SyliusCMSPlugin\Media\MediaItemMetadata;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Media\StoragePath;
use Setono\SyliusCMSPlugin\Media\UploadedFilename;
use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Repository\MediaFolderRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Step 3 of an import for its media, inside the import's one transaction and before its entries: it creates the
 * folders the plan creates, and creates or updates the media items it writes, with the export's UUIDs, metadata and
 * changedAt. Existing folders are neither moved nor renamed. A media item it creates gets the export's file, as an
 * upload does, and one it updates whose file differs gets it the way a replace in the library does: at a new storage
 * path, with its UUID kept, taking effect at once (ADR 0013). Its alt text and focal point are the export's, as the rest
 * of its metadata. The files aren't stored here: it gives them back, for the import to store once its plan is complete.
 * A replaced file's delayed delete, MediaItemReplaced and MediaItemSaved go out once the import commits. See "What an
 * import does" in docs/spec/transfer.md and "Replacing a file" in docs/spec/media.md
 *
 * @internal
 */
final readonly class MediaApplier
{
    /**
     * @param EntityManagerInterface $manager the manager of media items and folders
     * @param FactoryInterface<MediaItemInterface> $mediaItemFactory
     * @param FactoryInterface<MediaFolderInterface> $mediaFolderFactory
     */
    public function __construct(
        private EntityManagerInterface $manager,
        private MediaItemRepositoryInterface $mediaItems,
        private MediaFolderRepositoryInterface $mediaFolders,
        private FactoryInterface $mediaItemFactory,
        private FactoryInterface $mediaFolderFactory,
        private AfterCommitDispatcher $events,
        private AfterCommitMessageBus $messageBus,
        private ClockInterface $clock,
        private GracePeriod $gracePeriod,
    ) {
    }

    /**
     * @return PendingMediaFiles the files of the media items it pointed at new ones, to store once the plan is complete
     */
    public function apply(ReadExport $export, ImportPlan $plan): PendingMediaFiles
    {
        $folders = $this->folders($export, $plan);
        $files = new PendingMediaFiles();

        /** @var array<string, array{ReadMediaItem, MediaItemInterface, MediaItemMetadata|null}> $written by UUID, with an updated one's metadata before */
        $written = [];
        foreach ($plan->writtenMediaItems() as $planned) {
            $mediaItem = $export->mediaItems[$planned->uuid];
            if (ImportAction::Create === $planned->action) {
                $row = $this->create($mediaItem, $files);
                $before = null;
            } else {
                $row = $this->mediaItems->findOneByUuid($planned->uuid) ?? throw new \LogicException(sprintf('The plan updates the media item %s, which there\'s none of', $planned->uuid));
                // Locked and read again, as a replace in the library does, so a replace there meanwhile can't make it
                // delete a file other than the one it replaces
                $this->manager->lock($row, LockMode::PESSIMISTIC_WRITE);
                $this->manager->refresh($row);
                $before = MediaItemMetadata::of($row);
                $this->replaceFile($mediaItem, $row, $files);
            }

            $this->describe($mediaItem, $row, $folders);
            $written[$planned->uuid] = [$mediaItem, $row, $before];
        }

        $this->manager->flush();

        // A video's poster, once every media item it writes is there
        foreach ($written as [$mediaItem, $row, $before]) {
            $row->setPoster($this->poster($mediaItem, $written));
            if (null !== $before && !MediaItemMetadata::of($row)->equals($before)) {
                $this->events->dispatch(new MediaItemSaved($row->getUuid()));
            }
        }

        $this->manager->flush();

        return $files;
    }

    /**
     * The folders the media items it writes sit in, created when the plan creates them, by UUID
     *
     * @return array<string, MediaFolderInterface>
     */
    private function folders(ReadExport $export, ImportPlan $plan): array
    {
        $exported = [];
        foreach ($export->mediaFolders as $folder) {
            $exported[$folder->uuid] = $folder;
        }

        $folders = [];
        foreach ($plan->mediaFolders as $planned) {
            if (ImportAction::Create !== $planned->action) {
                $folders[$planned->uuid] = $this->mediaFolders->findOneByUuid($planned->uuid) ?? throw new \LogicException(sprintf('The plan uses the media folder %s, which there\'s none of', $planned->uuid));

                continue;
            }

            // The plan lists parents before their children
            $parent = $exported[$planned->uuid]->parent;
            $folder = $this->mediaFolderFactory->createNew();
            $folder->setUuid($planned->uuid);
            $folder->setName($exported[$planned->uuid]->name);
            $folder->setParent(null === $parent ? null : $folders[$parent] ?? $this->mediaFolders->findOneByUuid($parent));
            $this->manager->persist($folder);

            $folders[$planned->uuid] = $folder;
        }

        return $folders;
    }

    /**
     * A new media item of the file's kind with the export's UUID, pointed at the file as an upload is
     */
    private function create(ReadMediaItem $mediaItem, PendingMediaFiles $files): MediaItemInterface
    {
        $row = $this->mediaItemFactory->createNew();
        $row->setUuid($mediaItem->uuid());
        $row->setKind($mediaItem->file->kind);
        $this->pointAtFile($mediaItem, $row, $files);
        $this->manager->persist($row);

        return $row;
    }

    /**
     * Replaces the media item's file the way a replace in the library does, when the export's differs: a new storage
     * path, and the old file deleted by a delayed message once the grace period after the commit is over. A file the
     * target holds already stays, so its URLs do too
     */
    private function replaceFile(ReadMediaItem $mediaItem, MediaItemInterface $row, PendingMediaFiles $files): void
    {
        $filename = UploadedFilename::clean($mediaItem->exported->filename);
        if ($row->getChecksum() === $mediaItem->file->checksum) {
            $row->setOriginalFilename($filename);

            return;
        }

        $oldPath = $row->getPath();
        $this->pointAtFile($mediaItem, $row, $files);

        $this->messageBus->dispatch(
            new DeleteReplacedFile($row->getUuid(), $oldPath),
            [new DelayStamp($this->gracePeriod->delayInMilliseconds($this->clock->now()))],
        );
        $this->events->dispatch(new MediaItemReplaced($row->getUuid()));
    }

    private function pointAtFile(ReadMediaItem $mediaItem, MediaItemInterface $row, PendingMediaFiles $files): void
    {
        $filename = UploadedFilename::clean($mediaItem->exported->filename);
        $path = StoragePath::for($row->getUuid(), $filename, $mediaItem->file->extension);
        $mediaItem->file->applyTo($row, $filename, $path);
        $files->add($mediaItem->file, $path);
    }

    /**
     * The export's metadata, and its changedAt, as every write an import makes sets it
     *
     * @param array<string, MediaFolderInterface> $folders
     */
    private function describe(ReadMediaItem $mediaItem, MediaItemInterface $row, array $folders): void
    {
        $exported = $mediaItem->exported;
        $row->setName($exported->name);
        $row->setFolder(null === $exported->folder ? null : $folders[$exported->folder] ?? throw new \LogicException(sprintf('The plan has no media folder %s', $exported->folder)));
        $row->setCredit($exported->credit);

        $altTexts = $mediaItem->altTexts();
        foreach (array_unique([...array_keys($row->getAltTexts()), ...array_keys($altTexts)]) as $localeCode) {
            $row->setAltText((string) $localeCode, $altTexts[$localeCode] ?? null);
        }

        $focalPoint = $mediaItem->focalPoint();
        if (null !== $focalPoint) {
            $row->setFocalPoint($focalPoint);
        }

        $row->setChangedAt($exported->changedAt);
    }

    /**
     * A video's poster: the image media item with the export's poster's UUID, which the export brought along or the
     * target has
     *
     * @param array<string, array{ReadMediaItem, MediaItemInterface, MediaItemMetadata|null}> $written
     */
    private function poster(ReadMediaItem $mediaItem, array $written): ?MediaItemInterface
    {
        $uuid = $mediaItem->poster();
        if (null === $uuid) {
            return null;
        }

        $poster = isset($written[$uuid]) ? $written[$uuid][1] : $this->mediaItems->findOneByUuid($uuid);

        return MediaKind::Image === $poster?->getKind() ? $poster : null;
    }
}
