<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Homepage;

use Setono\SyliusCMSPlugin\Factory\HomepageFactoryInterface;
use Setono\SyliusCMSPlugin\Model\CodedRows;
use Setono\SyliusCMSPlugin\Model\Homepage;
use Setono\SyliusCMSPlugin\Model\Page;
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
        $page = self::page($page);

        CodedRows::sync(
            $page->getHomepages()->toArray(),
            array_values([...$channelCodes]),
            static fn (Homepage $homepage): string => $homepage->getChannelCode(),
            $page->removeHomepage(...),
            fn (string $code) => $page->addHomepage($this->factory->createForPage($page, $code)),
        );
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

    /**
     * The rows refer to the shipped Page, which the page resource's model is or extends
     */
    private static function page(PageInterface $page): Page
    {
        if (!$page instanceof Page) {
            throw new \LogicException(sprintf('The page %s must extend %s, which its homepage rows refer to', get_debug_type($page), Page::class));
        }

        return $page;
    }
}
