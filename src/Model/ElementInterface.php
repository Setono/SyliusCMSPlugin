<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;

interface ElementInterface extends ResourceInterface, CodeAwareInterface, InternalDescriptionAwareInterface
{
    public const TYPE_BLOCK = 'block';

    public const TYPE_CAROUSEL = 'carousel';

    public const TYPE_PAGE = 'page';

    public const TYPE_VIEW = 'view';

    public function getId(): ?int;

    public function getType(): string;

    public function getIdentifier(): string;
}
