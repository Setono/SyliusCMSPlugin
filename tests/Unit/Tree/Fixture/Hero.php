<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Tree\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Field\Media;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Model\Page;

#[Layout(code: 'hero', contentType: Page::class, label: 'Hero', template: 'hero.html.twig')]
final class Hero
{
    #[Media(label: 'Image')]
    public ?MediaItemInterface $image = null;
}
