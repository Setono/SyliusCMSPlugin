<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Model\ShareLinkInterface;
use Setono\SyliusCMSPlugin\ShareLink\ShareLinkUrlGenerator;

/**
 * A share link as the share_link_* endpoints answer with it: its id, its URL (null once the entry's draft has no URL in
 * its locale), its channel and locale, its fixed Shown to and Date (null for the time the link is opened), and when and
 * by whom it was made. Times are in UTC
 *
 * @internal
 */
final readonly class ShareLinkAnswer
{
    private const DATE_FORMAT = 'Y-m-d\TH:i:s\Z';

    public function __construct(private ShareLinkUrlGenerator $urlGenerator)
    {
    }

    public function __invoke(ShareLinkInterface $shareLink): ListedObject
    {
        return new ListedObject([
            'id' => $shareLink->getId(),
            'url' => $this->urlGenerator->generate($shareLink),
            'channel' => $shareLink->getChannelCode(),
            'locale' => $shareLink->getLocaleCode(),
            'shownTo' => $shareLink->getShownTo(),
            'date' => $shareLink->getDate()?->format(self::DATE_FORMAT),
            'createdAt' => $shareLink->getCreatedAt()->format(self::DATE_FORMAT),
            'createdBy' => $shareLink->getCreatedBy(),
        ]);
    }
}
