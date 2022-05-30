<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Component\Resource\Model\ResourceInterface;

interface NavigationItemInterface extends ResourceInterface
{
    public function getId(): ?int;
}
