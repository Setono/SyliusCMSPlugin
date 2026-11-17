<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Translation\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Link;
use Setono\SyliusCMSPlugin\Value\Link as LinkValue;

/**
 * A block type with a Link field of each shape: its label and URL per locale (the default), one link for every locale,
 * and a whole link per locale
 */
#[BlockType(code: 'link_shapes', label: 'app.link_shapes', template: 'link_shapes.html.twig')]
final class LinkShapes
{
    #[Link(label: 'app.link')]
    public ?LinkValue $link = null;

    #[Link(label: 'app.one_for_every_locale', translatable: false)]
    public ?LinkValue $oneForEveryLocale = null;

    #[Link(label: 'app.per_locale', translatable: true)]
    public ?LinkValue $perLocale = null;
}
