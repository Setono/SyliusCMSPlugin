<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

trait IdAwareTrait
{
    protected ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
