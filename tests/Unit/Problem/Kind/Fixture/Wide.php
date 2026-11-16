<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Setono\SyliusCMSPlugin\Model\Page;

/**
 * A project's layout for pages, with a field labelled as the plugin's Group block labels its own
 */
#[Layout(code: 'wide', contentType: Page::class, label: 'app.wide', template: 'wide.html.twig')]
final class Wide
{
    #[Toggle(label: 'setono_sylius_cms.field.group.full_width')]
    public bool $fullWidth = false;
}
