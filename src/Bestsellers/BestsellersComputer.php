<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Bestsellers;

use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Model\BestsellerScoreInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * Runs the bestsellers calculator for a channel and replaces the channel's scores with what it yields, in one
 * transaction, so a Bestsellers list never shows a channel half computed. The calculator runs before the transaction,
 * so one that asks an analytics service holds no lock while it waits. The rows are flushed and let go a batch at a
 * time, so the command's memory stays the same however many products sell. See "Bestsellers" in docs/spec/sources.md
 *
 * @internal
 */
final readonly class BestsellersComputer
{
    /** How many rows are flushed at once */
    private const BATCH_SIZE = 100;

    /**
     * @param FactoryInterface<BestsellerScoreInterface> $scoreFactory
     * @param class-string<BestsellerScoreInterface> $scoreClass
     */
    public function __construct(
        private BestsellersCalculatorInterface $calculator,
        private FactoryInterface $scoreFactory,
        private ManagerRegistry $managerRegistry,
        private string $scoreClass,
    ) {
    }

    /**
     * @return int how many products it scored in the channel
     */
    public function compute(ChannelInterface $channel): int
    {
        $channelCode = (string) $channel->getCode();

        // A product the calculator yields twice keeps its last score
        $scores = [];
        foreach ($this->calculator->calculate($channel) as $productCode => $score) {
            $scores[(string) $productCode] = (float) $score;
        }

        $manager = EntityManagers::of($this->managerRegistry, $this->scoreClass);
        $manager->wrapInTransaction(function () use ($manager, $channelCode, $scores): void {
            $manager->createQueryBuilder()
                ->delete($this->scoreClass, 'score')
                ->andWhere('score.channelCode = :channelCode')
                ->setParameter('channelCode', $channelCode)
                ->getQuery()
                ->execute()
            ;

            $batch = [];
            foreach ($scores as $productCode => $score) {
                $row = $this->scoreFactory->createNew();
                $row->setChannelCode($channelCode);
                $row->setProductCode((string) $productCode);
                $row->setScore($score);
                $manager->persist($row);
                $batch[] = $row;

                if (self::BATCH_SIZE === count($batch)) {
                    $manager->flush();
                    array_map($manager->detach(...), $batch);
                    $batch = [];
                }
            }

            $manager->flush();
            array_map($manager->detach(...), $batch);
        });

        return count($scores);
    }
}
