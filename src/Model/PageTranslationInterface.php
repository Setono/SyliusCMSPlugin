<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\SlugAwareInterface;
use Sylius\Component\Resource\Model\TranslationInterface;

interface PageTranslationInterface extends ResourceInterface, TranslationInterface, SlugAwareInterface
{
    public function getId(): ?int;

    public function getSlug(): ?string;

    public function setSlug(?string $slug): void;

    public function getTitle(): ?string;

    public function setTitle(?string $title): void;

    public function getMetaDescription(): ?string;

    public function setMetaDescription(?string $metaDescription): void;
}
