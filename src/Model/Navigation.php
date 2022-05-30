<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class Navigation extends Element implements NavigationInterface
{
    public function getType(): string
    {
        return ElementInterface::TYPE_NAVIGATION;
    }
}
