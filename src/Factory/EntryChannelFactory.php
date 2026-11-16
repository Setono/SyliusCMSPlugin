<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Factory;

use Setono\SyliusCMSPlugin\Model\EntryChannelInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Sylius\Resource\Exception\UnsupportedMethodException;

final readonly class EntryChannelFactory implements EntryChannelFactoryInterface
{
    /**
     * @param class-string<EntryChannelInterface> $className the entry_channel resource's model
     */
    public function __construct(private string $className)
    {
    }

    /**
     * A row always belongs to an entry and has a code
     *
     * @throws UnsupportedMethodException
     */
    public function createNew(): EntryChannelInterface
    {
        throw new UnsupportedMethodException('createNew');
    }

    public function createForEntry(EntryInterface $entry, string $code): EntryChannelInterface
    {
        return new $this->className($entry, $code);
    }
}
