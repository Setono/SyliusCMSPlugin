<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

class NavigationItem implements NavigationItemInterface
{
    protected ?int $id = null;

    protected ?int $lft = null;

    protected ?int $rgt = null;

    protected ?string $content = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
