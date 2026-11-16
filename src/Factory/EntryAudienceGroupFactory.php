<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Factory;

use Setono\SyliusCMSPlugin\Model\EntryAudienceGroupInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Sylius\Resource\Exception\UnsupportedMethodException;

final readonly class EntryAudienceGroupFactory implements EntryAudienceGroupFactoryInterface
{
    /**
     * @param class-string<EntryAudienceGroupInterface> $className the entry_audience_group resource's model
     */
    public function __construct(private string $className)
    {
    }

    /**
     * A row always belongs to an entry and has a code
     *
     * @throws UnsupportedMethodException
     */
    public function createNew(): EntryAudienceGroupInterface
    {
        throw new UnsupportedMethodException('createNew');
    }

    public function createForEntry(EntryInterface $entry, string $code): EntryAudienceGroupInterface
    {
        return new $this->className($entry, $code);
    }
}
