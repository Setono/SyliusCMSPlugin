<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Validation\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Model\Page;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A project's layout for pages with a required, translatable headline and a short shared badge
 */
#[Layout(code: 'campaign', contentType: Page::class, label: 'app.campaign', template: 'campaign.html.twig')]
final class Campaign
{
    #[Text(label: 'app.campaign.headline', required: true)]
    public ?string $headline = null;

    #[Text(label: 'app.campaign.badge', translatable: false)]
    #[Assert\Length(max: 5)]
    public ?string $badge = null;
}
