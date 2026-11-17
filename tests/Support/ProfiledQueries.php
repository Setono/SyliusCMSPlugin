<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpKernel\Profiler\Profile;

/**
 * The SQL a profiled request ran. The db collector holds everything since the kernel booted, so make one request first,
 * which boots the kernel afresh, and enable the profiler before the request to count
 */
trait ProfiledQueries
{
    /**
     * @return list<string>
     */
    private static function queries(KernelBrowser $client): array
    {
        $profile = $client->getProfile();
        self::assertInstanceOf(Profile::class, $profile, 'The request was profiled');
        $collector = $profile->getCollector('db');
        self::assertInstanceOf(DoctrineDataCollector::class, $collector);

        $byConnection = $collector->getQueries();
        self::assertIsArray($byConnection);

        $queries = [];
        foreach ($byConnection as $connectionQueries) {
            self::assertIsArray($connectionQueries);
            foreach ($connectionQueries as $query) {
                self::assertIsArray($query);
                self::assertIsString($query['sql']);
                $queries[] = $query['sql'];
            }
        }

        return $queries;
    }

    /**
     * The queries that read from the table
     *
     * @return list<string>
     */
    private static function queriesOf(KernelBrowser $client, string $table): array
    {
        return array_values(array_filter(self::queries($client), static fn (string $sql): bool => str_contains($sql, 'FROM ' . $table)));
    }
}
