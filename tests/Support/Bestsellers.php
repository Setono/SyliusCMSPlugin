<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\BestsellerScoreInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helpers for functional and UI tests that read or write the bestseller scores, which setono:cms:bestsellers:compute
 * otherwise writes
 */
trait Bestsellers
{
    abstract protected static function getContainer(): ContainerInterface;

    /**
     * Stores scores in the channel, as the command would
     *
     * @param array<string, int|float> $scores product code => score
     */
    protected static function scoreProducts(array $scores, string $channelCode = 'WEB'): void
    {
        /** @var FactoryInterface<BestsellerScoreInterface> $factory */
        $factory = self::getContainer()->get('setono_sylius_cms.factory.bestseller_score');
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.bestseller_score');
        foreach ($scores as $productCode => $score) {
            $row = $factory->createNew();
            $row->setChannelCode($channelCode);
            $row->setProductCode((string) $productCode);
            $row->setScore((float) $score);
            $manager->persist($row);
        }

        $manager->flush();
    }

    /**
     * The channel's scores, highest first
     *
     * @return array<string, float> product code => score
     */
    protected static function scoresOf(string $channelCode): array
    {
        /** @var RepositoryInterface<BestsellerScoreInterface> $repository */
        $repository = self::getContainer()->get('setono_sylius_cms.repository.bestseller_score');

        $scores = [];
        foreach ($repository->findBy(['channelCode' => $channelCode], ['score' => 'DESC', 'productCode' => 'ASC']) as $row) {
            $scores[$row->getProductCode()] = $row->getScore();
        }

        return $scores;
    }
}
