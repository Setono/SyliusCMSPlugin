<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusCMSPlugin\Field\Textarea;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;

/**
 * A store location's translation, with its translatable field, the directions to the store, in the column
 * store_location_directions
 */
#[ORM\Entity]
class StoreLocationTranslation extends EntryTranslation
{
    #[ORM\Column(type: 'text', nullable: true)]
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
