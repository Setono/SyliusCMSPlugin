<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\ResourceInterface;

interface ElementInterface extends ResourceInterface
{
    public function getIdentifier(): string;
}
