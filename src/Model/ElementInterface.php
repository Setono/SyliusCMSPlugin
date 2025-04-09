<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\TimestampableInterface;

interface ElementInterface extends CodeAwareInterface, InternalDescriptionAwareInterface, ResourceInterface, TimestampableInterface
{
    public const TYPE_ASSET = 'asset';

    public const TYPE_BLOCK = 'block';

    public const TYPE_CAROUSEL = 'carousel';

    public const TYPE_PAGE = 'page';

    public function getId(): ?int;
}
