<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Channel\Model\ChannelsAwareInterface;
use Sylius\Component\Resource\Model\ToggleableInterface;
use Sylius\Component\Resource\Model\TranslatableInterface;

interface PageInterface extends
    ToggleableInterface,
    EnabledDateIntervalAwareInterface,
    ChannelsAwareInterface,
    TranslatableInterface,
    ElementInterface
{
    public function getSlug(): ?string;

    public function setSlug(?string $slug): void;

    public function getTitle(): ?string;

    public function setTitle(?string $title): void;

    public function getMetaDescription(): ?string;

    public function setMetaDescription(?string $metaDescription): void;

    public function getContent(): ?string;

    public function setContent(?string $content): void;
}
