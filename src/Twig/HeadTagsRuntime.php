<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Collection\PageNumber;
use Setono\SyliusCMSPlugin\Localization\Availability;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Seo\AlternateLink;
use Setono\SyliusCMSPlugin\Seo\CanonicalUrlGeneratorInterface;
use Setono\SyliusCMSPlugin\Seo\HeadTags;
use Setono\SyliusCMSPlugin\Seo\Hreflang;
use Setono\SyliusCMSPlugin\Seo\MetaValuesInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * The head tags of an entry shown at its own URL, which layout/base.html.twig prints into the shop layout's title and
 * metatags blocks: cms_meta_title(entry) gives the title, as text, and cms_head_tags(entry) prints the rest, as HTML, so
 * a layout that calls it gets a tag added later without a change. Page 2 onwards of the entry's paginated Entries block
 * has a canonical URL of its own, with ?page=N, and no hreflang links. See "Head tags" in docs/spec/urls-and-seo.md
 */
final readonly class HeadTagsRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private MetaValuesInterface $metaValues,
        private CanonicalUrlGeneratorInterface $canonicalUrls,
        private Availability $availability,
    ) {
    }

    /**
     * The meta description, robots, the canonical link and the hreflang links, one per line, each left out when it has no
     * value
     *
     * @param array<string, mixed> $context
     */
    public function headTags(array $context, EntryInterface $entry): string
    {
        $tags = $this->tags($context, $entry);

        $html = [];
        if (null !== $tags->description) {
            $html[] = sprintf('<meta name="description" content="%s">', self::escape($tags->description));
        }

        if ($tags->noindex) {
            $html[] = '<meta name="robots" content="noindex">';
        }

        if (null !== $tags->canonicalUrl) {
            $html[] = sprintf('<link rel="canonical" href="%s">', self::escape($tags->canonicalUrl));
        }

        foreach ($tags->alternates as $alternate) {
            $html[] = sprintf('<link rel="alternate" hreflang="%s" href="%s">', self::escape($alternate->hreflang), self::escape($alternate->url));
        }

        return implode("\n", $html);
    }

    /**
     * The meta title, or else the title, each through the fallback chain, as text. Empty when neither has a value
     *
     * @param array<string, mixed> $context
     */
    public function metaTitle(array $context, EntryInterface $entry): string
    {
        return $this->tags($context, $entry)->title ?? '';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function tags(array $context, EntryInterface $entry): HeadTags
    {
        $renderContext = $context[CMSRuntime::RENDER_CONTEXT] ?? null;
        if (!$renderContext instanceof RenderContext) {
            throw new \LogicException(sprintf('The head tags can only be printed from a template that has the render context in "%s", such as a layout template', CMSRuntime::RENDER_CONTEXT));
        }

        $canonicalUrl = $this->canonicalUrls->generate($entry, $renderContext->channel, $renderContext->localeCode);

        return new HeadTags(
            $this->metaValues->title($entry, $renderContext->localeCode),
            $this->metaValues->description($entry, $renderContext->localeCode),
            $entry->isNoindex(),
            // Page 2 onwards of the paginated Entries block is a page of its own, and another locale's page N needn't hold
            // the same entries
            null === $canonicalUrl ? null : PageNumber::url($canonicalUrl, $renderContext->page),
            $renderContext->page > 1 ? [] : $this->alternates($entry, $renderContext->channel),
        );
    }

    /**
     * A link per locale of the channel the entry shows in, by locale code, then x-default to the channel's default
     * locale when it shows there. The entry is shown in the current locale, so it's published, enabled, inside its date
     * window, in the channel and in the shopper's audience, none of which depends on the locale: it shows in another
     * locale of the channel when it's available there. See "When an entry shows" in docs/spec/content-model.md
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
            $url = $this->availability->showsIn($entry, $localeCode) ? $this->canonicalUrls->generate($entry, $channel, $localeCode) : null;
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
     * As Twig escapes HTML and its attributes
     */
    private static function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
