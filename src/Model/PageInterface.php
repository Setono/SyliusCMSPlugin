<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Channel\Model\ChannelsAwareInterface;
use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\ToggleableInterface;

interface PageInterface extends ResourceInterface, ToggleableInterface, CodeAwareInterface, EnabledDateIntervalAwareInterface, ChannelsAwareInterface
{
    public function getId(): ?int;

    public function getView(): ?ViewInterface;

    public function setView(?ViewInterface $view): void;

    public function getTitle(): ?string;

    public function setTitle(?string $title): void;

    public function getMetaDescription(): ?string;

    public function setMetaDescription(?string $metaDescription): void;
}
