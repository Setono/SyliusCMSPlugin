<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\TimestampableInterface;

interface ElementInterface extends CodeAwareInterface, InternalDescriptionAwareInterface, ResourceInterface, TimestampableInterface
{
    public const TYPE_BLOCK = 'block';

    public const TYPE_CAROUSEL = 'carousel';

    public const TYPE_NAVIGATION = 'navigation';

    public const TYPE_PAGE = 'page';

    public const TYPE_VIEW = 'view';

    public function getId(): ?int;

    public function getType(): string;

    /**
     * The identifier MUST be unique across ALL classes implementing this interface
     */
    public function getIdentifier(): string;
}
