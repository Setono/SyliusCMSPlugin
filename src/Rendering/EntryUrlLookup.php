<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryChannelInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\HomepageInterface;
use Setono\SyliusCMSPlugin\Model\OldSlugInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Routing\EntryPath;
use Sylius\Component\Core\Model\ChannelInterface;

/**
 * Looks a URL up with one indexed query: for each content type with its own URL whose prefix fits the path, an entry
 * of that type in the channel with the rest of the path as its slug in the locale, or as one of its old slugs there.
 * A published entry's slug wins, then an old slug, which redirects, and then a never-published entry's slug, whose
 * controller answers 404: an old slug never blocks a new entry, and stops redirecting once another entry is published
 * at its URL. It selects scalars, not entities, because it runs in the route condition, before Sylius's locale context
 * is set. See "Routing" and "Old slugs" in docs/spec/urls-and-seo.md
 */
final readonly class EntryUrlLookup implements EntryUrlLookupInterface
{
    private const PUBLISHED = 0;

    private const OLD_SLUG = 1;

    private const NEVER_PUBLISHED = 2;

    public function __construct(
        private ManagerRegistry $managerRegistry,
        private ContentTypeRegistryInterface $contentTypes,
    ) {
    }

    public function lookup(ChannelInterface $channel, string $localeCode, string $path): ?EntryUrlMatch
    {
        // A content type switched off in setono_sylius_cms.disabled.content_types has no URLs
        $slugs = EntryPath::slugs($this->contentTypes->enabled(), $path);
        if ([] === $slugs) {
            return null;
        }

        $manager = EntityManagers::of($this->managerRegistry, Entry::class);
        $connection = $manager->getConnection();

        $parameters = ['locale' => $localeCode, 'channel' => (string) $channel->getCode()];
        $current = $old = [];
        foreach (array_keys($slugs) as $index => $contentType) {
            $current[] = sprintf('(entry.content_type = :content_type_%1$d AND translation.slug = :slug_%1$d)', $index);
            $old[] = sprintf('(entry.content_type = :content_type_%1$d AND old_slug.slug = :slug_%1$d)', $index);
            $parameters['content_type_' . $index] = $contentType;
            $parameters['slug_' . $index] = $slugs[$contentType];
        }

        $tables = [
            '{entry}' => $manager->getClassMetadata(Entry::class)->getTableName(),
            '{translation}' => $manager->getClassMetadata(EntryTranslation::class)->getTableName(),
            '{entry_channel}' => $manager->getClassMetadata(EntryChannelInterface::class)->getTableName(),
            '{old_slug}' => $manager->getClassMetadata(OldSlugInterface::class)->getTableName(),
            '{homepage}' => $manager->getClassMetadata(HomepageInterface::class)->getTableName(),
            '{published}' => (string) self::PUBLISHED,
            '{old}' => (string) self::OLD_SLUG,
            '{never_published}' => (string) self::NEVER_PUBLISHED,
            '{current_slugs}' => implode(' OR ', $current),
            '{old_slugs}' => implode(' OR ', $old),
        ];

        // A URL is unique per channel and locale, so entries with no channel in common can share one.
        // The newest old slug wins over older ones of other entries. A page that's the channel's homepage redirects to
        // the channel's root, from its URL and its old slugs alike
        $sql = strtr(<<<'SQL'
            SELECT uuid, content_type, current_slug, homepage FROM (
                SELECT entry.uuid, entry.content_type, NULL AS current_slug, 0 AS old_slug_id, entry.id AS entry_id,
                    CASE WHEN entry.last_published_at IS NULL THEN {never_published} ELSE {published} END AS priority,
                    CASE WHEN homepage.id IS NULL THEN 0 ELSE 1 END AS homepage
                FROM {entry} entry
                INNER JOIN {translation} translation ON translation.translatable_id = entry.id AND translation.locale = :locale
                INNER JOIN {entry_channel} entry_channel ON entry_channel.entry_id = entry.id AND entry_channel.code = :channel
                LEFT JOIN {homepage} homepage ON homepage.page_id = entry.id AND homepage.channel_code = :channel
                WHERE {current_slugs}
                UNION ALL
                SELECT entry.uuid, entry.content_type, translation.slug AS current_slug, old_slug.id AS old_slug_id, entry.id AS entry_id,
                    {old} AS priority,
                    CASE WHEN homepage.id IS NULL THEN 0 ELSE 1 END AS homepage
                FROM {old_slug} old_slug
                INNER JOIN {entry} entry ON entry.id = old_slug.entry_id AND entry.last_published_at IS NOT NULL
                INNER JOIN {translation} translation ON translation.translatable_id = entry.id AND translation.locale = old_slug.locale AND translation.slug IS NOT NULL
                INNER JOIN {entry_channel} entry_channel ON entry_channel.entry_id = entry.id AND entry_channel.code = :channel
                LEFT JOIN {homepage} homepage ON homepage.page_id = entry.id AND homepage.channel_code = :channel
                WHERE old_slug.locale = :locale AND ({old_slugs})
            ) url
            ORDER BY priority ASC, old_slug_id DESC, entry_id ASC
            SQL, $tables);

        $row = $connection->executeQuery($connection->getDatabasePlatform()->modifyLimitQuery($sql, 1), $parameters)->fetchAssociative();
        if (false === $row || !is_string($row['uuid']) || !is_string($row['content_type'])) {
            return null;
        }

        $currentSlug = $row['current_slug'] ?? null;

        return new EntryUrlMatch(
            $row['uuid'],
            $row['content_type'],
            is_string($currentSlug) ? EntryPath::ofSlug($this->contentTypes->get($row['content_type']), $currentSlug) : null,
            in_array($row['homepage'] ?? null, [1, '1'], true),
        );
    }
}
