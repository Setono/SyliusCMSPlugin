<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Factory;

use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryAudienceGroup;
use Sylius\Resource\Exception\UnsupportedMethodException;

final readonly class EntryAudienceGroupFactory implements EntryAudienceGroupFactoryInterface
{
    /**
     * @param class-string<EntryAudienceGroup> $className the entry_audience_group resource's model
     */
    public function __construct(private string $className)
    {
    }

    /**
     * A row always belongs to an entry and has a code
     *
     * @throws UnsupportedMethodException
     */
    public function createNew(): EntryAudienceGroup
    {
        throw new UnsupportedMethodException('createNew');
    }

    public function createForEntry(Entry $entry, string $code): EntryAudienceGroup
    {
        return new $this->className($entry, $code);
    }
}
