<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * Decorates the application's logger in a fixture kernel, keeping what's logged during the kernel's request, so a
 * functional test can read it with debug off, where Symfony's profiler collects no logs
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<array-key, mixed>}> */
    private array $records = [];

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];

        $this->logger->log($level, $message, $context);
    }

    /**
     * The contexts of what was logged at the level
     *
     * @return list<array<array-key, mixed>>
     */
    public function contextsAt(string $level): array
    {
        return array_values(array_map(
            static fn (array $record): array => $record['context'],
            array_filter($this->records, static fn (array $record): bool => $level === $record['level']),
        ));
    }
}
