<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Model\Page;

/**
 * A project's layout for pages with a Text field, which a binding can fill, labelled as the plugin's Heading block
 * labels its own
 */
#[Layout(code: 'tagline', contentType: Page::class, label: 'app.tagline', template: 'tagline.html.twig')]
final class Tagline
{
    #[Text(label: 'setono_sylius_cms.field.heading.text')]
    public ?string $tagline = null;
}
