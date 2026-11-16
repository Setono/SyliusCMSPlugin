<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ShareLinkInterface;
use Setono\SyliusCMSPlugin\Repository\ShareLinkRepositoryInterface;
use Setono\SyliusCMSPlugin\ShareLink\ShareLinkCreatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helpers for functional and UI tests that need share links without making them in the admin
 */
trait ShareLinks
{
    abstract protected static function getContainer(): ContainerInterface;

    /**
     * Makes a share link of the entry as the admin does, with Shown to and Date at the preview's starting values
     */
    protected static function createShareLink(EntryInterface $entry, string $channel = 'WEB', string $locale = 'en_US'): ShareLinkInterface
    {
        /** @var ShareLinkCreatorInterface $creator */
        $creator = self::getContainer()->get(ShareLinkCreatorInterface::class);

        return $creator->create($entry, $channel, $locale);
    }

    /**
     * The entry's share links as the database holds them, newest first
     *
     * @return list<ShareLinkInterface>
     */
    protected static function shareLinksOf(EntryInterface $entry): array
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.share_link');
        $manager->clear();

        /** @var ShareLinkRepositoryInterface $shareLinks */
        $shareLinks = self::getContainer()->get(ShareLinkRepositoryInterface::class);
        $reloaded = $manager->find($entry::class, $entry->getId());
        self::assertInstanceOf(EntryInterface::class, $reloaded);

        return $shareLinks->findByEntry($reloaded);
    }

    protected static function revokeShareLink(ShareLinkInterface $shareLink): void
    {
        /** @var ShareLinkRepositoryInterface $shareLinks */
        $shareLinks = self::getContainer()->get(ShareLinkRepositoryInterface::class);
        $shareLinks->remove($shareLink);
    }
}
