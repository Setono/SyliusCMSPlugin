<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Repository\HomepageRepositoryInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;

/**
 * "Homepage of" per channel, which the URLs section of a page's overview page lists through
 * cms_admin_channel_homepages(): each of the store's channels, in the order the builder lists them, with the page that's
 * its homepage now, and then the deleted channels the page is still the homepage of. Only a page can be a homepage.
 * See "Entry overview page" in docs/spec/admin.md
 */
final readonly class ChannelHomepages
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private ChannelRepositoryInterface $channelRepository,
        private HomepageRepositoryInterface $homepages,
    ) {
    }

    /**
     * @return list<ChannelHomepage>
     */
    public function of(EntryInterface $entry): array
    {
        if (!$entry instanceof PageInterface) {
            return [];
        }

        $codes = $names = [];
        foreach ($this->channelRepository->findBy([], ['id' => 'ASC']) as $channel) {
            $code = $channel->getCode() ?? '';
            $codes[] = $code;
            $names[$code] = $channel->getName() ?? $code;
        }

        $codes = array_values(array_unique([...$codes, ...$entry->getHomepageChannelCodes()]));

        $pages = [];
        foreach ($this->homepages->findByChannelCodes($codes) as $homepage) {
            $pages[$homepage->getChannelCode()] = $homepage->getPage();
        }

        $channelHomepages = [];
        foreach ($codes as $code) {
            $page = $pages[$code] ?? null;
            $channelHomepages[] = new ChannelHomepage($code, $names[$code] ?? $code, $page, null !== $page && $page->getUuid() === $entry->getUuid());
        }

        return $channelHomepages;
    }
}
