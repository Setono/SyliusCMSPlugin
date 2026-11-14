<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Builder\ChangeSet;

final readonly class RemoveBlock implements Change
{
    public function __construct(public string $key)
    {
    }
}
