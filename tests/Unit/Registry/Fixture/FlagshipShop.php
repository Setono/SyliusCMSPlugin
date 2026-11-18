<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Field\Text;

/**
 * A project's subclass of Shop's model, with a field of its own beside Shop's private ones
 */
class FlagshipShop extends Shop
{
    #[Text(label: 'app.manager')]
    private ?string $manager = null;

    public function getManager(): ?string
    {
        return $this->manager;
    }

    public function setManager(?string $manager): void
    {
        $this->manager = $manager;
    }
}
