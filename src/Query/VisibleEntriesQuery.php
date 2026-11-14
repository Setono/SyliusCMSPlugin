<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Query;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroup;
use Setono\SyliusCMSPlugin\Model\EntryChannel;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Targeting\AudienceMode;

/**
 * Filters on "When an entry shows" (docs/spec/content-model.md) in SQL that MySQL and PostgreSQL both run. The rows only
 * ever hold published content, so it needs no stage filter (ADR 0009). Its own parameters and aliases start with
 * "cms_visible_", so callers can add theirs
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
        $modelClass = $this->contentTypes->get($contentType)->modelClass;

        $manager = $this->managerRegistry->getManagerForClass($modelClass);
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(sprintf('%s must be managed by the Doctrine ORM', $modelClass));
        }

        return $manager->createQueryBuilder()
            ->select('entry')
            ->from($modelClass, 'entry')
            // 1. Published, and 2. enabled
            ->andWhere('entry.lastPublishedAt IS NOT NULL')
            ->andWhere('entry.enabled = :cms_visible_enabled')
            ->setParameter('cms_visible_enabled', true, Types::BOOLEAN)
            // 3. Inside the date window: "Show from" is inclusive and "Show until" exclusive. The columns hold UTC
            ->andWhere('entry.showFrom IS NULL OR entry.showFrom <= :cms_visible_now')
            ->andWhere('entry.showUntil IS NULL OR entry.showUntil > :cms_visible_now')
            ->setParameter('cms_visible_now', $context->now->setTimezone(new \DateTimeZone('UTC')), Types::DATETIME_IMMUTABLE)
            // 4. In the channel
            ->andWhere(sprintf(
                'EXISTS (SELECT cms_visible_channel.id FROM %s cms_visible_channel WHERE cms_visible_channel.entry = entry AND cms_visible_channel.code = :cms_visible_channel)',
                EntryChannel::class,
            ))
            ->setParameter('cms_visible_channel', (string) $context->channel->getCode())
            // 5. With a translation in the locale. Held-back translations come with #232
            ->andWhere(sprintf(
                'EXISTS (SELECT cms_visible_translation.id FROM %s cms_visible_translation WHERE cms_visible_translation.translatable = entry AND cms_visible_translation.locale = :cms_visible_locale)',
                EntryTranslation::class,
            ))
            ->setParameter('cms_visible_locale', $context->localeCode)
            // 6. In the shopper's audience (ADR 0010)
            ->andWhere(sprintf(
                'entry.audienceMode = :cms_visible_everyone'
                . ' OR (entry.audienceMode = :cms_visible_only AND EXISTS (%s))'
                . ' OR (entry.audienceMode = :cms_visible_except AND NOT EXISTS (%s))',
                self::audienceGroupOfShopper('cms_visible_only_group'),
                self::audienceGroupOfShopper('cms_visible_except_group'),
            ))
            ->setParameter('cms_visible_everyone', AudienceMode::Everyone->value)
            ->setParameter('cms_visible_only', AudienceMode::Only->value)
            ->setParameter('cms_visible_except', AudienceMode::Except->value)
            ->setParameter('cms_visible_customer_group', $context->customerGroup)
        ;
    }

    /**
     * The entry's audience group row that holds the shopper's customer group value. Each subquery needs an alias of its own
     */
    private static function audienceGroupOfShopper(string $alias): string
    {
        return sprintf(
            'SELECT %1$s.id FROM %2$s %1$s WHERE %1$s.entry = entry AND %1$s.code = :cms_visible_customer_group',
            $alias,
            EntryAudienceGroup::class,
        );
    }
}
