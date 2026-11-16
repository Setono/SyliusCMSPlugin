<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;

/**
 * One customer group value in an entry's audience: a customer group code (ADR 0002), "@guest" or "@no_group", as a row
 * beside the entry so the visible-entries query filters on it in SQL (ADR 0010). Read and change an entry's audience
 * through EntryInterface
 */
interface EntryAudienceGroupInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getEntry(): EntryInterface;

    public function getCode(): string;
}
