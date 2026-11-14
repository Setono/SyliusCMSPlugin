<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Runs work on an entry's working copy in a transaction, with the entry's row locked and read again. Saves, discards
 * and publishes of one entry wait for each other, and loading waits for any of them in flight, so what they read is
 * what they write, and a lock token always matches the working copy it came with
 *
 * @internal
 */
final readonly class EntryLock
{
    public function __construct(private ManagerRegistry $managerRegistry)
    {
    }

    /**
     * Runs the work while no write runs on the entry
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function read(EntryInterface $entry, callable $work): mixed
    {
        return $this->run($entry, LockMode::PESSIMISTIC_READ, $work);
    }

    /**
     * Runs the work while nothing else runs on the entry, and flushes what it changed before committing
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function write(EntryInterface $entry, callable $work): mixed
    {
        return $this->run($entry, LockMode::PESSIMISTIC_WRITE, $work, flush: true);
    }

    /**
     * @template T
     *
     * @param LockMode::PESSIMISTIC_* $lockMode
     * @param callable(): T $work
     *
     * @return T
     */
    private function run(EntryInterface $entry, int $lockMode, callable $work, bool $flush = false): mixed
    {
        $manager = $this->managerRegistry->getManagerForClass($entry::class);
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(sprintf('No Doctrine entity manager manages %s', $entry::class));
        }

        $connection = $manager->getConnection();
        $connection->beginTransaction();

        try {
            $manager->lock($entry, $lockMode);
            $manager->refresh($entry);

            $result = $work();
            if ($flush) {
                $manager->flush();
            }

            $connection->commit();

            return $result;
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }
    }
}
