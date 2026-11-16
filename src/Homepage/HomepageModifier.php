<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Homepage;

use Setono\SyliusCMSPlugin\Factory\HomepageFactoryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;

/**
 * Works out which rows to add and which to remove, and keeps the rows of the channels that stay: Doctrine removes rows
 * after it inserts them, so a new row for a channel that stays would clash with the old one's unique channel code
 */
final readonly class HomepageModifier implements HomepageModifierInterface
{
    public function __construct(
        private HomepageFactoryInterface $factory,
        private HomepageRepositoryInterface $repository,
    ) {
    }

    public function setHomepageOf(PageInterface $page, iterable $channelCodes): void
    {
        $codes = [...$channelCodes];

        $kept = [];
        foreach ($page->getHomepages()->toArray() as $homepage) {
            if (in_array($homepage->getChannelCode(), $codes, true)) {
                $kept[] = $homepage->getChannelCode();
            } else {
                $page->removeHomepage($homepage);
            }
        }

        foreach (array_unique(array_diff($codes, $kept)) as $code) {
            $page->addHomepage($this->factory->createForPage($page, $code));
        }
    }

    public function takeOffOtherPages(PageInterface $page, iterable $channelCodes): array
    {
        $codes = array_values(array_unique([...$channelCodes]));
        if ([] === $codes) {
            return [];
        }

        $takenFrom = [];
        foreach ($this->repository->findByChannelCodes($codes) as $homepage) {
            $other = $homepage->getPage();
            if ($other === $page) {
                continue;
            }

            $other->removeHomepage($homepage);
            $takenFrom[$other->getUuid()] = $other;
        }

        return array_values($takenFrom);
    }
}
