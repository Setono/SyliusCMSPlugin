<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Application\Field;

use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Field\Field;

/**
 * The test application's own field type, as a project adds one: a store's opening hours per weekday, and a photo of the
 * store from the media library. Its handler is OpeningHoursHandler, and editors enter it with its field control, the
 * app-opening-hours custom element that public/cms/opening-hours.js defines and setono_sylius_cms.builder.scripts loads
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'app_opening_hours', translatable: false, bindable: [], control: 'app-opening-hours')]
final class OpeningHours extends Field
{
}
