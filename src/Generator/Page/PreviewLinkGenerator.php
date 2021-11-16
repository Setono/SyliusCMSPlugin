<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Generator\Page;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageTranslationInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PreviewLinkGenerator implements PreviewLinkGeneratorInterface
{
    private UrlGeneratorInterface $router;

    public function __construct(UrlGeneratorInterface $router)
    {
        $this->router = $router;
    }

    public function generateAll(PageInterface $page): iterable
    {
        $links = [];

        $channels = $page->getChannels();
        /** @var ChannelInterface $channel */
        foreach ($channels as $channel) {
            $locales = $channel->getLocales();
            foreach ($locales as $locale) {
                /** @var PageTranslationInterface $translation */
                $translation = $page->getTranslation($locale->getCode());
                $slug = $translation->getSlug();
                if ($translation->getLocale() === $locale->getCode() && null !== $slug) {
                    $url = $this->router->generate('setono_sylius_cms_shop_page_show', [
                        'slug' => $slug,
                        '_locale' => $locale->getCode(),
                        '_channel_code' => $channel->getCode(),
                        'preview' => 1,
                    ]);

                    $links[] = new PreviewLink($channel, $locale, $url);
                }
            }
        }

        return $links;
    }
}
