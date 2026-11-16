<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Routing\EntryPath;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The URLs of an entry per locale, which the URLs section of its overview page lists through cms_admin_entry_urls():
 * the URL each locale has in the entry's rows, and the one its draft gives it once published, when that's another.
 * Only an entry whose content type has its own URL has URLs, and only in the locales it has a slug in.
 * See "Entry overview page" in docs/spec/admin.md
 */
final readonly class EntryUrls
{
    public function __construct(
        private DraftRepositoryInterface $drafts,
        private ContentTypeRegistryInterface $contentTypes,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return list<EntryUrl> by locale code
     */
    public function of(EntryInterface $entry): array
    {
        $contentType = $this->contentTypes->getByEntry($entry);
        if (!$contentType->hasOwnUrl()) {
            return [];
        }

        $rows = TitlesAndSlugs::ofRows($entry);
        // Only a published entry has a draft
        $draft = $entry->isPublished() ? $this->drafts->findOneByEntry($entry) : null;
        $drafted = null === $draft ? $rows : TitlesAndSlugs::ofDraft($draft);

        $localeCodes = array_unique([...array_keys($rows), ...array_keys($drafted)]);
        sort($localeCodes);

        $urls = [];
        foreach ($localeCodes as $localeCode) {
            $url = $this->url($contentType, $localeCode, $rows[$localeCode]['slug'] ?? null);
            $draftUrl = $this->url($contentType, $localeCode, $drafted[$localeCode]['slug'] ?? null);
            if (null !== $url || null !== $draftUrl) {
                $urls[] = new EntryUrl($localeCode, $url, $draftUrl === $url ? null : $draftUrl);
            }
        }

        return $urls;
    }

    private function url(ContentTypeMetadata $contentType, string $localeCode, ?string $slug): ?string
    {
        $path = EntryPath::ofSlug($contentType, $slug);

        return null === $path ? null : $this->urlGenerator->generate('setono_sylius_cms_shop_entry', ['_locale' => $localeCode, 'path' => $path]);
    }
}
