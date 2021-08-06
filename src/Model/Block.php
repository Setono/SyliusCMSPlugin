<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\TranslatableInterface;
use Sylius\Component\Resource\Model\TranslatableTrait;
use Sylius\Component\Resource\Model\TranslationInterface;

class Block implements BlockInterface, TranslatableInterface
{
    use TranslatableTrait {
        __construct as private initializeTranslationsCollection;
        getTranslation as private doGetTranslation;
    }

    protected ?int $id = null;

    protected ?string $code = null;

    public function __construct()
    {
        $this->initializeTranslationsCollection();
    }

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

    public function getContent(): ?string
    {
        return $this->getTranslation()->getContent();
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
