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
    ) {
    }

    public function getWorkingCopy(EntryInterface $entry): EntryInterface
    {
        if (!$entry->isPublished()) {
            return $entry;
        }

        $workingCopy = clone $entry;

        $draft = $this->draftRepository->findOneByEntry($entry);
        if (null !== $draft) {
            $this->entryDenormalizer->denormalize(CanonicalJson::decode($draft->getContent()), $workingCopy);
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

    public function applyDraft(EntryInterface $entry): void
    {
        $draft = $this->draftRepository->findOneByEntry($entry);
        if (null === $draft) {
            return;
        }

        $this->entryDenormalizer->denormalize(CanonicalJson::decode($draft->getContent()), $entry);
        $this->manager->remove($draft);
    }

    private function createDraft(EntryInterface $entry): DraftInterface
    {
        $draft = $this->draftFactory->createNew();
        $draft->setEntry($entry);

        return $draft;
    }
}
