<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\AbstractTranslation;

class BlockTranslation extends AbstractTranslation implements BlockTranslationInterface
{
    protected ?int $id = null;

    protected ?string $content = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
    }
}
