<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Query;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroupInterface;
use Setono\SyliusCMSPlugin\Model\EntryChannelInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Targeting\AudienceMode;

/**
 * Filters on "When an entry shows" (docs/spec/content-model.md) in SQL that MySQL and PostgreSQL both run. The rows only
 * ever hold published content, so it needs no stage filter (ADR 0009). Its own parameters and aliases start with
 * "cms_visible_", so callers can add theirs. Each rule is one condition, which the visible queries require and
 * whyNotVisible() checks in order
 */
final readonly class VisibleEntriesQuery implements VisibleEntriesQueryInterface
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private ContentTypeRegistryInterface $contentTypes,
    ) {
    }

    public function createQueryBuilder(string $contentType, RenderContext $context): QueryBuilder
    {
        $queryBuilder = $this->entries($contentType, $context)->select('entry');
        foreach (self::rules() as $condition) {
            $queryBuilder->andWhere($condition);
        }

        // A content type switched off in setono_sylius_cms.disabled.content_types is hidden from sources and routing
        if ($this->contentTypes->get($contentType)->disabled) {
            $queryBuilder->andWhere('1 = 0');
        }

        return $queryBuilder;
    }

    public function whyNotVisible(string $contentType, array $uuids, RenderContext $context): array
    {
        if ([] === $uuids) {
            return [];
        }

        // Every entry of a content type switched off is as hidden as a disabled entry
        $firstFailed = $this->contentTypes->get($contentType)->disabled ? sprintf("CASE WHEN 1 = 1 THEN '%s'", NotVisibleReason::Disabled->value) : 'CASE';
        foreach (self::rules() as $reason => $condition) {
            $firstFailed .= sprintf(" WHEN NOT (%s) THEN '%s'", $condition, $reason);
        }
        $firstFailed .= " ELSE '' END";

        /** @var list<array{uuid: string, reason: string}> $rows */
        $rows = $this->entries($contentType, $context)
            ->select('entry.uuid AS uuid', $firstFailed . ' AS reason')
            ->andWhere('entry.uuid IN (:cms_visible_uuids)')
            ->setParameter('cms_visible_uuids', $uuids)
            ->getQuery()
            ->getScalarResult()
        ;

        $reasons = [];
        foreach ($rows as $row) {
            $reason = NotVisibleReason::tryFrom($row['reason']);
            if (null !== $reason) {
                $reasons[$row['uuid']] = $reason;
            }
        }

        return $reasons;
    }

    /**
     * The content type's entries, with the parameters of the rules
     */
    private function entries(string $contentType, RenderContext $context): QueryBuilder
    {
        $modelClass = $this->contentTypes->get($contentType)->modelClass;

        return EntityManagers::of($this->managerRegistry, $modelClass)->createQueryBuilder()
            ->from($modelClass, 'entry')
            ->setParameter('cms_visible_enabled', true, Types::BOOLEAN)
            // The columns hold UTC
            ->setParameter('cms_visible_now', $context->now->setTimezone(new \DateTimeZone('UTC')), Types::DATETIME_IMMUTABLE)
            ->setParameter('cms_visible_channel', (string) $context->channel->getCode())
            ->setParameter('cms_visible_locale', $context->localeCode)
            ->setParameter('cms_visible_no_title', '')
            ->setParameter('cms_visible_shown', true, Types::BOOLEAN)
            ->setParameter('cms_visible_everyone', AudienceMode::Everyone->value)
            ->setParameter('cms_visible_only', AudienceMode::Only->value)
            ->setParameter('cms_visible_except', AudienceMode::Except->value)
            ->setParameter('cms_visible_customer_group', $context->customerGroup)
        ;
    }

    /**
     * The condition a visible entry meets for each rule, by the reason an entry that doesn't meet it isn't visible, in
     * the order of "When an entry shows"
     *
     * @return array<value-of<NotVisibleReason>, string>
     */
    private static function rules(): array
    {
        return [
            // 1. Published
            NotVisibleReason::NotPublished->value => 'entry.lastPublishedAt IS NOT NULL',
            // 2. Enabled
            NotVisibleReason::Disabled->value => 'entry.enabled = :cms_visible_enabled',
            // 3. Inside the date window: "Show from" is inclusive and "Show until" exclusive
            NotVisibleReason::OutsideDateWindow->value => '(entry.showFrom IS NULL OR entry.showFrom <= :cms_visible_now)'
                . ' AND (entry.showUntil IS NULL OR entry.showUntil > :cms_visible_now)',
            // 4. In the channel
            NotVisibleReason::NotInChannel->value => sprintf(
                'EXISTS (SELECT cms_visible_channel.id FROM %s cms_visible_channel WHERE cms_visible_channel.entry = entry AND cms_visible_channel.code = :cms_visible_channel)',
                EntryChannelInterface::class,
            ),
            // 5. With a translation in the locale, which it has once that translation has a title, and that isn't held
            // back. Whole entries never fall back to another locale
            NotVisibleReason::NotTranslated->value => sprintf(
                'EXISTS (SELECT cms_visible_translation.id FROM %s cms_visible_translation WHERE cms_visible_translation.translatable = entry'
                . ' AND cms_visible_translation.locale = :cms_visible_locale'
                . ' AND cms_visible_translation.title IS NOT NULL AND cms_visible_translation.title <> :cms_visible_no_title'
                . ' AND cms_visible_translation.shown = :cms_visible_shown)',
                EntryTranslation::class,
            ),
            // 6. In the shopper's audience (ADR 0010)
            NotVisibleReason::OutsideAudience->value => sprintf(
                'entry.audienceMode = :cms_visible_everyone'
                . ' OR (entry.audienceMode = :cms_visible_only AND EXISTS (%s))'
                . ' OR (entry.audienceMode = :cms_visible_except AND NOT EXISTS (%s))',
                self::audienceGroupOfShopper('cms_visible_only_group'),
                self::audienceGroupOfShopper('cms_visible_except_group'),
            ),
        ];
    }

    /**
     * The entry's audience group row that holds the shopper's customer group value. Each subquery needs an alias of its own
     */
    private static function audienceGroupOfShopper(string $alias): string
    {
        return sprintf(
            'SELECT %1$s.id FROM %2$s %1$s WHERE %1$s.entry = entry AND %1$s.code = :cms_visible_customer_group',
            $alias,
            EntryAudienceGroupInterface::class,
        );
    }
}
