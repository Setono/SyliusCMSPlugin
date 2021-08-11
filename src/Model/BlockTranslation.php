<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\AbstractTranslation;

class BlockTranslation extends AbstractTranslation implements BlockTranslationInterface
{
    protected ?int $id = null;

    protected ?string $content = null;

    protected ?string $rawContent = null;

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

    public function getRawContent(): ?string
    {
        return $this->rawContent;
    }

    public function setRawContent(?string $rawContent): void
    {
        $this->rawContent = $rawContent;
    }
}
