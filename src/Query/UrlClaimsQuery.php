<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Query;

use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryChannelInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Routing\UrlClaim;

/**
 * Reads scalars with DBAL, in SQL that MySQL and PostgreSQL both run. A draft is JSON in a text column, so the drafts
 * whose JSON holds one of the slugs are found with LIKE, and then read to check that the slug is a translation's
 */
final readonly class UrlClaimsQuery implements UrlClaimsQueryInterface
{
    private const LIKE_ESCAPE = '!';

    public function __construct(private ManagerRegistry $managerRegistry)
    {
    }

    public function published(string $localeCode, array $slugs, string $exceptUuid): array
    {
        return $this->rows($localeCode, $slugs, $exceptUuid, true);
    }

    public function drafted(string $localeCode, array $slugs, string $exceptUuid): array
    {
        return [...$this->rows($localeCode, $slugs, $exceptUuid, false), ...$this->drafts($localeCode, $slugs, $exceptUuid)];
    }

    /**
     * The entries whose rows have the URL: published ones, whose rows are their published content, or never-published
     * ones, whose rows are their working copy
     *
     * @param array<string, string> $slugs
     *
     * @return list<UrlClaim>
     */
    private function rows(string $localeCode, array $slugs, string $exceptUuid, bool $published): array
    {
        if ([] === $slugs) {
            return [];
        }

        $manager = $this->manager();
        $query = $this->entries($manager, $exceptUuid)
            ->addSelect('translation.slug', 'translation.title')
            ->innerJoin('entry', $manager->getClassMetadata(EntryTranslation::class)->getTableName(), 'translation', 'translation.translatable_id = entry.id')
            ->andWhere('translation.locale = :locale')
            ->andWhere($published ? 'entry.last_published_at IS NOT NULL' : 'entry.last_published_at IS NULL')
            ->setParameter('locale', $localeCode)
        ;

        $conditions = [];
        foreach (array_keys($slugs) as $index => $contentType) {
            $conditions[] = sprintf('(entry.content_type = :content_type_%1$d AND translation.slug = :slug_%1$d)', $index);
            $query->setParameter('content_type_' . $index, $contentType);
            $query->setParameter('slug_' . $index, $slugs[$contentType]);
        }
        $query->andWhere(implode(' OR ', $conditions));

        $claims = [];
        foreach ($query->executeQuery()->iterateAssociative() as $row) {
            $claims[] = ['id' => (int) $row['id'], 'uuid' => (string) $row['uuid'], 'content_type' => (string) $row['content_type'], 'slug' => (string) $row['slug'], 'title' => is_string($row['title']) ? $row['title'] : null];
        }

        return $this->withChannels($manager, $localeCode, $claims);
    }

    /**
     * The published entries whose draft gives the URL to a translation
     *
     * @param array<string, string> $slugs
     *
     * @return list<UrlClaim>
     */
    private function drafts(string $localeCode, array $slugs, string $exceptUuid): array
    {
        if ([] === $slugs) {
            return [];
        }

        $manager = $this->manager();
        $query = $this->entries($manager, $exceptUuid)
            ->addSelect('draft.content')
            ->innerJoin('entry', $manager->getClassMetadata(DraftInterface::class)->getTableName(), 'draft', 'draft.entry_id = entry.id')
        ;
        $query->andWhere(sprintf('entry.content_type IN (%s)', self::list($query, 'content_type', array_keys($slugs))));

        $conditions = [];
        foreach (array_values(array_unique($slugs)) as $index => $slug) {
            $conditions[] = sprintf("draft.content LIKE :draft_slug_%d ESCAPE '%s'", $index, self::LIKE_ESCAPE);
            $query->setParameter('draft_slug_' . $index, '%' . self::escapeLike('"slug":' . CanonicalJson::encode($slug)) . '%');
        }
        $query->andWhere(implode(' OR ', $conditions));

        $claims = [];
        foreach ($query->executeQuery()->iterateAssociative() as $row) {
            $contentType = (string) $row['content_type'];
            $fields = self::draftedFields((string) $row['content'], $localeCode);
            if (($fields['slug'] ?? null) !== ($slugs[$contentType] ?? null)) {
                continue;
            }

            $claims[] = ['id' => (int) $row['id'], 'uuid' => (string) $row['uuid'], 'content_type' => $contentType, 'slug' => $slugs[$contentType], 'title' => is_string($fields['title'] ?? null) ? $fields['title'] : null];
        }

        return $this->withChannels($manager, $localeCode, $claims);
    }

    private function entries(EntityManagerInterface $manager, string $exceptUuid): QueryBuilder
    {
        return $manager->getConnection()->createQueryBuilder()
            ->select('entry.id', 'entry.uuid', 'entry.content_type')
            ->from($manager->getClassMetadata(Entry::class)->getTableName(), 'entry')
            ->andWhere('entry.uuid <> :except')
            ->setParameter('except', $exceptUuid)
            ->orderBy('entry.id')
        ;
    }

    /**
     * Adds each entry's channels. An entry in no channel has its URL nowhere, so it's left out
     *
     * @param list<array{id: int, uuid: string, content_type: string, slug: string, title: string|null}> $claims
     *
     * @return list<UrlClaim>
     */
    private function withChannels(EntityManagerInterface $manager, string $localeCode, array $claims): array
    {
        if ([] === $claims) {
            return [];
        }

        $query = $manager->getConnection()->createQueryBuilder()
            ->select('entry_channel.entry_id', 'entry_channel.code')
            ->from($manager->getClassMetadata(EntryChannelInterface::class)->getTableName(), 'entry_channel')
            ->orderBy('entry_channel.code')
        ;
        $query->andWhere(sprintf('entry_channel.entry_id IN (%s)', self::list($query, 'entry_id', array_column($claims, 'id'))));
        $rows = $query->executeQuery()->iterateAssociative();

        $channels = [];
        foreach ($rows as $row) {
            $channels[(int) $row['entry_id']][] = (string) $row['code'];
        }

        $result = [];
        foreach ($claims as $claim) {
            if (isset($channels[$claim['id']])) {
                $result[] = new UrlClaim($claim['uuid'], $claim['content_type'], $localeCode, $claim['slug'], $claim['title'], $channels[$claim['id']]);
            }
        }

        return $result;
    }

    /**
     * The fields of the locale's translation in a draft, which is the entry in the entry format
     *
     * @return array<array-key, mixed>
     */
    private static function draftedFields(string $content, string $localeCode): array
    {
        try {
            $data = CanonicalJson::decode($content);
        } catch (\JsonException) {
            return [];
        }

        $fields = is_array($data) && is_array($data['translations'] ?? null) && is_array($data['translations'][$localeCode] ?? null)
            ? $data['translations'][$localeCode]['fields'] ?? null
            : null;

        return is_array($fields) ? $fields : [];
    }

    /**
     * The values as a list of named parameters, which every DBAL version the plugin allows binds alike
     *
     * @param list<int|string> $values
     */
    private static function list(QueryBuilder $query, string $name, array $values): string
    {
        $parameters = [];
        foreach ($values as $index => $value) {
            $parameters[] = sprintf(':%s_%d', $name, $index);
            $query->setParameter(sprintf('%s_%d', $name, $index), $value);
        }

        return implode(', ', $parameters);
    }

    private static function escapeLike(string $value): string
    {
        return strtr($value, [self::LIKE_ESCAPE => self::LIKE_ESCAPE . self::LIKE_ESCAPE, '%' => self::LIKE_ESCAPE . '%', '_' => self::LIKE_ESCAPE . '_']);
    }

    private function manager(): EntityManagerInterface
    {
        return EntityManagers::of($this->managerRegistry, Entry::class);
    }
}
