<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preset\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Setono\SyliusCMSPlugin\Model\Page;

/**
 * A project's layout for pages, with a translatable field and a shared one
 */
#[Layout(code: 'campaign', contentType: Page::class, label: 'app.campaign', template: 'campaign.html.twig', version: 2)]
final class Campaign
{
    #[Text(label: 'app.tagline')]
    public ?string $tagline = null;

    #[Toggle(label: 'app.dark')]
    public bool $dark = false;
}
