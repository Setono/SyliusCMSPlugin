<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\EntryFormat\EntryDenormalizer;
use Setono\SyliusCMSPlugin\EntryFormat\EntryNormalizer;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Sylius\Resource\Factory\FactoryInterface;

final readonly class WorkingCopyManager implements WorkingCopyManagerInterface
{
    /**
     * @param FactoryInterface<DraftInterface> $draftFactory
     * @param ObjectManager $manager the manager of drafts, which is also the manager of entries
     */
    public function __construct(
        private DraftRepositoryInterface $draftRepository,
        private FactoryInterface $draftFactory,
        private EntryNormalizer $entryNormalizer,
        private EntryDenormalizer $entryDenormalizer,
        private ObjectManager $manager,
        private StoredDataUpgraderInterface $upgrader,
    ) {
    }

    /**
     * The working copy holds its tree upgraded: a never-published entry's rows are upgraded in place, so the next save
     * writes them upgraded, and a published entry's rows are left as they are
     */
    public function getWorkingCopy(EntryInterface $entry): EntryInterface
    {
        if (!$entry->isPublished()) {
            $entry->setBlockTree($this->upgrader->readBlockTree($entry));

            return $entry;
        }

        $workingCopy = clone $entry;

        $draft = $this->draftRepository->findOneByEntry($entry);
        if (null === $draft) {
            $workingCopy->setBlockTree($this->upgrader->readBlockTree($entry));
        } else {
            $this->entryDenormalizer->denormalize($this->readDraft($draft), $workingCopy);
        }

        return $workingCopy;
    }

    public function save(EntryInterface $entry, EntryInterface $workingCopy): void
    {
        if ($entry->isPublished()) {
            $draft = $this->draftRepository->findOneByEntry($entry) ?? $this->createDraft($entry);
            $draft->setContent(CanonicalJson::encode($this->entryNormalizer->normalize($workingCopy)));
            $this->manager->persist($draft);
        }

        $this->manager->flush();
    }

    public function hasDraft(EntryInterface $entry): bool
    {
        return null !== $this->draftRepository->findOneByEntry($entry);
    }

    public function discard(EntryInterface $entry): void
    {
        $draft = $this->draftRepository->findOneByEntry($entry);
        if (null === $draft) {
            return;
        }

        $this->manager->remove($draft);
        $this->manager->flush();
    }

    public function applyToRows(EntryInterface $entry, EntryInterface $workingCopy): void
    {
        // A never-published entry is its own working copy. Another goes through the entry format, as a draft does
        if ($workingCopy !== $entry) {
            $this->entryDenormalizer->denormalize(CanonicalJson::decode(CanonicalJson::encode($this->entryNormalizer->normalize($workingCopy))), $entry);
        }

        $draft = $this->draftRepository->findOneByEntry($entry);
        if (null !== $draft) {
            $this->manager->remove($draft);
        }
    }

    /**
     * The draft in the entry format, upgraded. What isn't an entry is left for EntryDenormalizer to refuse
     */
    private function readDraft(DraftInterface $draft): mixed
    {
        $data = CanonicalJson::decode($draft->getContent());

        return is_array($data) ? $this->upgrader->upgradeEntry($data)->data : $data;
    }

    private function createDraft(EntryInterface $entry): DraftInterface
    {
        $draft = $this->draftFactory->createNew();
        $draft->setEntry($entry);

        return $draft;
    }
}
