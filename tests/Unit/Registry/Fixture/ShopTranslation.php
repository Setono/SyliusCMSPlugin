<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Field\Textarea;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;

/**
 * The translations of Shop, with a translatable field
 */
class ShopTranslation extends EntryTranslation
{
    #[Textarea(label: 'app.cms.field.directions')]
    private ?string $directions = null;

    public function getDirections(): ?string
    {
        return $this->directions;
    }

    public function setDirections(?string $directions): void
    {
        $this->directions = $directions;
    }
}
