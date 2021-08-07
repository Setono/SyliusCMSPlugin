<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Template;

interface RegistryFactoryInterface
{
    public function create(): RegistryInterface;
}
