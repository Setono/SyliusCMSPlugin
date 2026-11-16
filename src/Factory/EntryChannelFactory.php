<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Factory;

use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryChannel;
use Sylius\Resource\Exception\UnsupportedMethodException;

final readonly class EntryChannelFactory implements EntryChannelFactoryInterface
{
    /**
     * @param class-string<EntryChannel> $className the entry_channel resource's model
     */
    public function __construct(private string $className)
    {
    }

    /**
     * A row always belongs to an entry and has a code
     *
     * @throws UnsupportedMethodException
     */
    public function createNew(): EntryChannel
    {
        throw new UnsupportedMethodException('createNew');
    }

    public function createForEntry(Entry $entry, string $code): EntryChannel
    {
        return new $this->className($entry, $code);
    }
}
