<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\ToggleableInterface;

// todo add code (can be used in template later to style the page easily
interface PageInterface extends ResourceInterface, ToggleableInterface
{
    public function getId(): ?int;

    public function getView(): ?ViewInterface;

    public function setView(?ViewInterface $view): void;

    public function getTitle(): ?string;

    public function setTitle(?string $title): void;

    public function getMetaDescription(): ?string;

    public function setMetaDescription(?string $metaDescription): void;
}
