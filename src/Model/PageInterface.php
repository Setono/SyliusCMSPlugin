<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Channel\Model\ChannelsAwareInterface;
use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\ToggleableInterface;
use Sylius\Component\Resource\Model\TranslatableInterface;

interface PageInterface extends
    ResourceInterface,
    ToggleableInterface,
    CodeAwareInterface,
    EnabledDateIntervalAwareInterface,
    ChannelsAwareInterface,
    TranslatableInterface,
    InternalDescriptionAwareInterface,
    ElementInterface
{
    public function getId(): ?int;

    public function getView(): ?ViewInterface;

    public function setView(?ViewInterface $view): void;

    public function getSlug(): ?string;

    public function setSlug(?string $slug): void;

    public function getTitle(): ?string;

    public function setTitle(?string $title): void;

    public function getMetaDescription(): ?string;

    public function setMetaDescription(?string $metaDescription): void;
}
