<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin\Grid;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusCMSPlugin\Model\EntryChannelInterface;
use Sylius\Bundle\GridBundle\Doctrine\DataSourceInterface as DoctrineDataSourceInterface;
use Sylius\Component\Grid\Data\DataSourceInterface;
use Sylius\Component\Grid\Filtering\FilterInterface;

/**
 * The grid filter type "setono_sylius_cms_entry_channel": the entry lists' channel filter, by channel code. An entry
 * is in a channel when it has a channel row with its code, which a subquery finds, so an entry is listed once
 */
final class ChannelFilter implements FilterInterface
{
    public const TYPE = 'setono_sylius_cms_entry_channel';

    public function apply(DataSourceInterface $dataSource, string $name, $data, array $options): void
    {
        if (!is_string($data) || '' === $data || !$dataSource instanceof DoctrineDataSourceInterface) {
            return;
        }

        $queryBuilder = $dataSource->getQueryBuilder();
        if (!$queryBuilder instanceof QueryBuilder) {
            return;
        }

        $queryBuilder
            ->andWhere(sprintf(
                'EXISTS (SELECT cms_channel.id FROM %s cms_channel WHERE cms_channel.entry = %s AND cms_channel.code = :cms_channel)',
                EntryChannelInterface::class,
                $queryBuilder->getRootAliases()[0],
            ))
            ->setParameter('cms_channel', $data)
        ;
    }
}
