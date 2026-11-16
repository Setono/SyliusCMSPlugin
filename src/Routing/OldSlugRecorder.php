<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Routing;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\OldSlugInterface;
use Setono\SyliusCMSPlugin\Repository\OldSlugRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * A locale whose translation lost its slug has no URL to redirect to, so its slug isn't kept
 */
final readonly class OldSlugRecorder implements OldSlugRecorderInterface
{
    /**
     * @param FactoryInterface<OldSlugInterface> $oldSlugFactory
     * @param ObjectManager $manager the manager of old slugs, which is also the manager of entries
     */
    public function __construct(
        private OldSlugRepositoryInterface $oldSlugs,
        private FactoryInterface $oldSlugFactory,
        private ObjectManager $manager,
    ) {
    }

    public function record(EntryInterface $entry, array $before, array $after): void
    {
        $changed = array_filter(
            $before,
            static fn (string $slug, string $localeCode): bool => isset($after[$localeCode]) && $after[$localeCode] !== $slug,
            \ARRAY_FILTER_USE_BOTH,
        );

        // Most publishes change no slug, and then nothing is read
        if ([] === $changed) {
            return;
        }

        $kept = [];
        foreach ($this->oldSlugs->findByEntry($entry) as $oldSlug) {
            $localeCode = $oldSlug->getLocaleCode();
            if (($after[$localeCode] ?? null) === $oldSlug->getSlug()) {
                $this->manager->remove($oldSlug);

                continue;
            }

            $kept[$localeCode][] = $oldSlug->getSlug();
        }

        foreach ($changed as $localeCode => $slug) {
            if (in_array($slug, $kept[$localeCode] ?? [], true)) {
                continue;
            }

            $oldSlug = $this->oldSlugFactory->createNew();
            $oldSlug->setEntry($entry);
            $oldSlug->setLocaleCode($localeCode);
            $oldSlug->setSlug($slug);

            $this->manager->persist($oldSlug);
        }
    }
}
