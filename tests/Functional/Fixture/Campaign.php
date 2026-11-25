<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Attribute\Layout;
use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Targeting\ChannelChoicesProvider;

/**
 * A project's layout for pages with a required headline, which publish refuses empty, and the channel the campaign runs
 * in, one of the store's. CampaignLayoutKernel registers it
 */
#[Layout(code: 'campaign', contentType: Page::class, label: 'app.cms.layout.campaign', template: 'cms/layout/campaign.html.twig')]
final class Campaign
{
    #[Text(label: 'app.cms.field.headline', required: true)]
    public ?string $headline = null;

    #[Choice(label: 'app.cms.field.channel', choicesProvider: ChannelChoicesProvider::class)]
    public ?string $channel = null;
}
