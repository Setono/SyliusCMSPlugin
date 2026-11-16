<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslationInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Seo\AlternateLink;
use Setono\SyliusCMSPlugin\Seo\CanonicalUrlGeneratorInterface;
use Setono\SyliusCMSPlugin\Seo\HeadTags;
use Setono\SyliusCMSPlugin\Seo\Hreflang;
use Setono\SyliusCMSPlugin\Seo\MetaValues;
use Sylius\Component\Core\Model\ChannelInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * cms_head_tags(entry): the head tags of an entry shown at its own URL, which layout/base.html.twig prints into the shop
 * layout's title and metatags blocks. See "Head tags" in docs/spec/urls-and-seo.md
 */
final readonly class HeadTagsRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private MetaValues $metaValues,
        private CanonicalUrlGeneratorInterface $canonicalUrls,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function headTags(array $context, EntryInterface $entry): HeadTags
    {
        $renderContext = $context[CMSRuntime::RENDER_CONTEXT] ?? null;
        if (!$renderContext instanceof RenderContext) {
            throw new \LogicException(sprintf('The head tags can only be printed from a template that has the render context in "%s", such as a layout template', CMSRuntime::RENDER_CONTEXT));
        }

        return new HeadTags(
            $this->metaValues->title($entry, $renderContext->localeCode),
            $this->metaValues->description($entry, $renderContext->localeCode),
            $entry->isNoindex(),
            $this->canonicalUrls->generate($entry, $renderContext->channel, $renderContext->localeCode),
            $this->alternates($entry, $renderContext->channel),
        );
    }

    /**
     * A link per locale of the channel the entry shows in, by locale code, then x-default to the channel's default
     * locale when it shows there
     *
     * @return list<AlternateLink>
     */
    private function alternates(EntryInterface $entry, ChannelInterface $channel): array
    {
        $localeCodes = [];
        foreach ($channel->getLocales() as $locale) {
            $localeCodes[] = $locale->getCode() ?? '';
        }
        sort($localeCodes);

        $urls = [];
        foreach ($localeCodes as $localeCode) {
            $url = self::showsIn($entry, $localeCode) ? $this->canonicalUrls->generate($entry, $channel, $localeCode) : null;
            if (null !== $url) {
                $urls[$localeCode] = $url;
            }
        }

        $links = array_map(static fn (string $localeCode, string $url): AlternateLink => new AlternateLink(Hreflang::of($localeCode), $url), array_keys($urls), $urls);

        $default = $urls[$channel->getDefaultLocale()?->getCode() ?? ''] ?? null;
        if (null !== $default) {
            $links[] = new AlternateLink(Hreflang::X_DEFAULT, $default);
        }

        return $links;
    }

    /**
     * The entry is shown in the current locale, so it's published, enabled, inside its date window, in the channel and
     * in the shopper's audience, none of which depends on the locale. It shows in another locale of the channel when it
     * has a translation there, which it has once that translation has a title, and that translation isn't held back:
     * the rule VisibleEntriesQuery filters on. See "When an entry shows" in docs/spec/content-model.md
     */
    private static function showsIn(EntryInterface $entry, string $localeCode): bool
    {
        $translation = $entry->getTranslations()->get($localeCode);

        return $translation instanceof EntryTranslationInterface && null !== $translation->getTitle() && '' !== $translation->getTitle() && $translation->isShown();
    }
}
