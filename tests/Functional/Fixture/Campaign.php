<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Model\Page;

/**
 * A project's layout for pages with a required headline, which publish refuses empty. CampaignLayoutKernel registers it
 */
#[Layout(code: 'campaign', contentType: Page::class, label: 'app.cms.layout.campaign', template: 'cms/layout/campaign.html.twig')]
final class Campaign
{
    #[Text(label: 'app.cms.field.headline', required: true)]
    public ?string $headline = null;
}
