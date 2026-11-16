<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Builder\ChangeSet\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Field\RichText;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Value\RichText as RichTextValue;

/**
 * A layout for pages with translatable fields, one of them rich text, and a shared one
 */
#[Layout(code: 'banner', contentType: Page::class, label: 'app.banner', template: 'banner.html.twig', version: 3)]
final class Banner
{
    #[Text(label: 'app.tagline')]
    public ?string $tagline = null;

    #[RichText(label: 'app.intro')]
    public ?RichTextValue $intro = null;

    #[Toggle(label: 'app.dark')]
    public bool $dark = false;
}
