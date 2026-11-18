<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Publishing\PublishSchedulerInterface;
use Setono\SyliusCMSPlugin\Tests\Support\ScheduledPublishes;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * The Content hub opens on its Overview: what needs attention, what's coming up, how complete the translations are and
 * which drafts are in progress. See "Overview" in docs/spec/admin.md
 */
final class OverviewTest extends UITestCase
{
    use ClockSensitiveTrait;
    use ScheduledPublishes;

    private const PROMO = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e50';

    /**
     * @test
     */
    public function it_shows_a_scheduled_publish_under_coming_up_and_drafts_under_drafts_in_progress_and_follows_what_needs_attention_to_its_entry(): void
    {
        self::mockTime('2030-01-01 10:00:00');
        self::forgetSentMessages();
        $this->signInAsAdmin();
        $launch = self::createPage('Launch', 'launch', 'Coming soon');
        /** @var PublishSchedulerInterface $scheduler */
        $scheduler = self::getContainer()->get(PublishSchedulerInterface::class);
        $scheduler->schedule($launch, new \DateTimeImmutable('2030-01-02 09:00:00'));
        $about = self::createPage('About us', 'about-us', 'Hello', published: true);
        self::saveChanges($about, [['op' => 'set_entry_value', 'field' => 'title', 'locale' => 'en_US', 'value' => 'About us, edited']]);
        $contact = self::createPageWithBlocks('Contact', 'contact', [new StoredBlock(self::PROMO, 'promo', 1)]);

        // Content opens the hub on its Overview
        $browser = $this->visit('/admin/');
        $browser->locator('.sylius-admin-menu')->getByRole('link', ['name' => 'Content'])->click();
        Expect::locator($browser->getByRole('heading', ['name' => 'Content', 'level' => 1]))->toBeVisible();

        $comingUp = $browser->getByRole('region', ['name' => 'Coming up']);
        Expect::locator($comingUp->locator('[data-event]'))->toHaveCount(1);
        Expect::locator($comingUp->locator('[data-event]'))->toContainText('Launch');
        Expect::locator($comingUp->locator('[data-event]'))->toContainText('Publishes');
        Expect::locator($comingUp->locator('[data-event] time'))->toHaveText('2030-01-02 09:00');

        // Both a published entry with unpublished changes and one never published
        $drafts = $browser->getByRole('region', ['name' => 'Drafts in progress']);
        Expect::locator($drafts->getByRole('link', ['name' => 'About us, edited']))->toBeVisible();
        Expect::locator($drafts->locator(sprintf('[data-entry="%s"] [data-entry-status]', $about->getUuid())))->toContainText('Unpublished changes');
        Expect::locator($drafts->getByRole('link', ['name' => 'Contact']))->toBeVisible();
        Expect::locator($drafts->locator(sprintf('[data-entry="%s"] [data-entry-status]', $contact->getUuid())))->toContainText('Never published');

        // A problem leads to its entry
        $needsAttention = $browser->getByRole('region', ['name' => 'Needs attention']);
        Expect::locator($needsAttention)->toContainText('Blocks of removed types');
        Expect::locator($needsAttention)->toContainText('A block of the type "promo", which the code no longer has, isn\'t shown.');
        $needsAttention->getByRole('link', ['name' => 'Contact'])->click();

        Expect::locator($browser->locator('[data-overview-header]')->getByRole('heading', ['level' => 1]))->toContainText('Contact');
        self::assertStringEndsWith(sprintf('/admin/cms/entry/%s', $contact->getUuid()), $browser->url());
        Expect::locator($browser->getByRole('region', ['name' => 'Problems']))->toContainText('A block of the type "promo", which the code no longer has, isn\'t shown.');
    }
}
