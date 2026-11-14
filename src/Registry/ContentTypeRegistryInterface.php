<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

interface ContentTypeRegistryInterface
{
    public function has(string $code): bool;

    /**
     * @throws UnknownContentTypeException
     */
    public function get(string $code): ContentTypeMetadata;

    /**
     * @throws UnknownContentTypeException
     */
    public function getByEntry(EntryInterface $entry): ContentTypeMetadata;

    /**
     * @return list<ContentTypeMetadata>
     */
    public function all(): array;
}
