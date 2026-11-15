<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;

/**
 * One channel an entry is in, by the channel's code (ADR 0002), as a row beside the entry so the visible-entries query
 * and the URL lookup filter on it in SQL. Read and change an entry's channels through EntryInterface
 */
interface EntryChannelInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getEntry(): EntryInterface;

    public function getCode(): string;
}
