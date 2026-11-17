<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Application\Block;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Tests\Application\Field\OpeningHours;
use Setono\SyliusCMSPlugin\Tests\Application\Value\OpeningHours as OpeningHoursValue;

/**
 * The test application's own block type with a field of its own field type, opening hours
 */
#[BlockType(code: 'app_store_hours', label: 'app.cms.block_type.store_hours', template: 'cms/block/store_hours.html.twig')]
final class StoreHours
{
    #[OpeningHours(label: 'app.cms.field.opening_hours', help: 'app.cms.field.opening_hours_help')]
    public ?OpeningHoursValue $hours = null;
}
