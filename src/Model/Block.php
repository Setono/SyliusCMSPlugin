<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\TranslatableTrait;
use Sylius\Component\Resource\Model\TranslationInterface;

class Block extends Element implements BlockInterface
{
    use TranslatableTrait {
        __construct as private initializeTranslationsCollection;

        getTranslation as private doGetTranslation;
    }

    protected ?string $defaultContent = null;

    protected ?string $defaultRawContent = null;

    public function __construct()
    {
        $this->initializeTranslationsCollection();
    }

    public function getType(): string
    {
        return ElementInterface::TYPE_BLOCK;
    }

    public function getDefaultContent(): ?string
    {
        return $this->defaultContent;
    }

    public function setDefaultContent(?string $defaultContent): void
    {
        $this->defaultContent = $defaultContent;
    }

    public function getDefaultRawContent(): ?string
    {
        return $this->defaultRawContent;
    }

    public function setDefaultRawContent(?string $defaultRawContent): void
    {
        $this->defaultRawContent = $defaultRawContent;
    }

    public function getContent(): ?string
    {
        $content = $this->getTranslation()->getContent();
        if (null === $content || '' === $content) {
            return $this->getDefaultContent();
        }

        return $content;
    }

    public function setContent(?string $content): void
    {
        $this->getTranslation()->setContent($content);
    }

    /**
     * @return BlockTranslationInterface
     */
    public function getTranslation(?string $locale = null): TranslationInterface
    {
        /** @var BlockTranslationInterface $translation */
        $translation = $this->doGetTranslation($locale);

        return $translation;
    }

    protected function createTranslation(): BlockTranslationInterface
    {
        return new BlockTranslation();
    }
}
