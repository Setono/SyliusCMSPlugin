<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Revision;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\EntryFormat\EntryNormalizer;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\RevisionInterface;
use Setono\SyliusCMSPlugin\Security\EditorName;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class RevisionRecorder implements RevisionRecorderInterface
{
    /**
     * @param FactoryInterface<RevisionInterface> $revisionFactory
     * @param ObjectManager $manager the manager of revisions, which is also the manager of entries
     */
    public function __construct(
        private FactoryInterface $revisionFactory,
        private EntryNormalizer $entryNormalizer,
        private ObjectManager $manager,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function record(EntryInterface $entry, \DateTimeImmutable $publishedAt, bool $scheduled = false, ?string $publishedBy = null): void
    {
        $revision = $this->revisionFactory->createNew();
        $revision->setEntry($entry);
        $revision->setContent(CanonicalJson::encode($this->entryNormalizer->normalize($entry)));
        $revision->setPublishedAt($publishedAt);
        $revision->setPublishedBy($publishedBy ?? EditorName::signedIn($this->tokenStorage));
        $revision->setScheduled($scheduled);

        $this->manager->persist($revision);
    }
}
