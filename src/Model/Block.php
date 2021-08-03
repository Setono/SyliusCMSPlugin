<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class Block implements BlockInterface
{
    protected ?int $id = null;

    protected ?string $code = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }
}
