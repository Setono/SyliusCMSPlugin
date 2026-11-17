<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Support;

use Symfony\Component\Clock\Clock;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * The test application's Messenger transports in the test env, with the DSN "kept-in-memory://": in memory, like
 * Symfony's in-memory transport, but kept when the kernel resets its services between requests. A UI test's builder
 * sends several requests after the one that schedules a publish, which would empty Symfony's own. A delayed message
 * becomes available by the clock, which tests mock. Tests read and clear them through SentMessages
 *
 * @implements TransportFactoryInterface<InMemoryTransport>
 */
final class KeptInMemoryTransportFactory implements TransportFactoryInterface
{
    public const DSN = 'kept-in-memory://';

    /** @var array<string, InMemoryTransport> by transport name */
    private static array $transports = [];

    /**
     * @param array<array-key, mixed> $options
     */
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        $name = $options['transport_name'] ?? $dsn;

        return self::transport(is_string($name) ? $name : $dsn);
    }

    /**
     * @param array<array-key, mixed> $options
     */
    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, self::DSN);
    }

    /**
     * The transport of that name, which holds what was sent to it since it was last cleared
     */
    public static function transport(string $name): InMemoryTransport
    {
        return self::$transports[$name] ??= new InMemoryTransport(null, new Clock());
    }

    /**
     * Forgets what every transport was sent
     */
    public static function clear(): void
    {
        foreach (self::$transports as $transport) {
            $transport->reset();
        }
    }
}
