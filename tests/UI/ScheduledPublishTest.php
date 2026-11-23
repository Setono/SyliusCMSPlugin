<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Page\PageInterface as BrowserPage;
use Setono\SyliusCMSPlugin\Tests\Support\ScheduledPublishes;
use Setono\SyliusCMSPlugin\Tests\Support\ServerTimeZone;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Scheduling a publish from the builder and the overview page, with the server's clock mocked, and the Messenger
 * worker running it when its time comes. See "Scheduled publishes" in docs/spec/publishing.md
 */
final class ScheduledPublishTest extends UITestCase
{
    use ClockSensitiveTrait;
    use ScheduledPublishes;
    use ServerTimeZone;

    /**
     * @test
     */
    public function it_lets_an_editor_schedule_a_publish_cancel_it_to_edit_and_schedule_it_again_for_the_worker_to_publish(): void
    {
        self::mockTime('2030-01-01 10:00:00');
        self::forgetSentMessages();
        $this->signInAsAdmin();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $builder = sprintf('/admin/cms/entry/%s/builder', $page->getUuid());
        $browser = $this->visit($builder);

        // The time is entered, and shown, in PHP's default time zone, UTC in the tests
        $this->schedule($browser, '2030-01-02T10:00');

        // The builder is read-only until the schedule is cancelled
        Expect::locator($browser->getByText('Publishes 2030-01-02 10:00', ['exact' => true]))->toBeVisible();
        Expect::locator($browser->getByText('The draft is locked: it publishes 2030-01-02 10:00 (UTC). Cancel the schedule to edit it.'))->toBeVisible();
        Expect::locator($browser->getByRole('button', ['name' => 'Publish', 'exact' => true]))->toHaveCount(0);
        Expect::locator($browser->getByRole('button', ['name' => 'Add block']))->toHaveCount(0);
        $browser->getByRole('button', ['name' => 'Heading', 'exact' => false])->first()->click();
        Expect::locator($browser->getByLabel('Text'))->toBeDisabled();
        self::assertNotNull(self::scheduledPublishOf($page));

        // Cancelling unlocks it
        $browser->getByRole('button', ['name' => 'Cancel the schedule'])->click();
        Expect::locator($browser->getByRole('button', ['name' => 'Publish', 'exact' => true]))->toBeVisible();
        Expect::locator($browser->getByText('Publishes 2030-01-02 10:00', ['exact' => true]))->toHaveCount(0);
        self::assertNull(self::scheduledPublishOf($page));

        $browser->getByLabel('Text')->fill('Hello world');
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');

        // Scheduled again, the worker publishes it when its time comes
        $this->schedule($browser, '2030-01-02T10:00');
        self::mockTime('2030-01-03 10:00:00');
        // The message of the cancelled schedule does nothing, the other one publishes
        self::assertSame(2, self::consumeMessages());

        $browser = $this->visit($builder);
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();
        Expect::locator($browser->getByRole('button', ['name' => 'Publish', 'exact' => true]))->toBeVisible();
        Expect::locator($browser->getByText('The draft is locked', ['exact' => false]))->toHaveCount(0);

        $shop = $this->visit('/en_US/about-us');
        Expect::locator($shop->locator('.cms-tree .cms-heading'))->toHaveText('Hello world');
        self::assertTrue(self::reloadPage($page)->isPublished());
    }

    /**
     * @test
     */
    public function it_schedules_a_publish_from_the_overview_page_and_offers_publish_now_once_it_is_overdue(): void
    {
        self::mockTime('2030-01-01 10:00:00');
        self::forgetSentMessages();
        $this->signInAsAdmin();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $overview = sprintf('/admin/cms/entry/%s', $page->getUuid());

        // The overview page shows and reads times in the server's time zone, UTC in the tests
        $browser = $this->visit($overview);
        $publishing = $browser->getByRole('region', ['name' => 'Publishing']);
        // playwright-php's getByLabel() finds the label, so the input is found by its type
        Expect::locator($publishing->locator('input[type="datetime-local"]'))->toHaveAccessibleDescription('UTC');
        $publishing->getByLabel('Publish at')->fill('2030-01-01T11:00');
        // Semantic UI's icon glyph is part of a button's name, so the names don't match exactly
        $publishing->getByRole('button', ['name' => 'Schedule'])->click();

        Expect::locator($browser->locator('.sylius-flash-message'))->toContainText('The publish has been scheduled for 2030-01-01 11:00 (UTC).');
        Expect::locator($browser->locator('[data-overview-header] [data-status]'))->toContainText('Publishes 2030-01-01 11:00');
        $publishing = $browser->getByRole('region', ['name' => 'Publishing']);
        Expect::locator($publishing)->toContainText('The draft is locked until then.');
        Expect::locator($publishing->getByRole('button', ['name' => 'Publish']))->toHaveCount(0);
        Expect::locator($publishing->getByRole('button', ['name' => 'Cancel the schedule']))->toBeVisible();

        // The worker isn't running, so five minutes after its time it's overdue
        self::mockTime('2030-01-01 11:05:00');
        $browser = $this->visit($overview);
        $publishing = $browser->getByRole('region', ['name' => 'Publishing']);
        Expect::locator($publishing)->toContainText('The Messenger worker may not be running.');
        Expect::locator($browser->locator('[data-overview-header] [data-status]'))->toContainText('Scheduled publish overdue');
        Expect::locator($browser->getByRole('region', ['name' => 'Problems']))->toContainText('hasn\'t run');

        $publishing->getByRole('button', ['name' => 'Publish now'])->click();

        Expect::locator($browser->locator('.sylius-flash-message'))->toContainText('The entry has been published.');
        Expect::locator($browser->getByRole('region', ['name' => 'Publishing']))->not()->toContainText('Messenger');
        self::assertTrue(self::reloadPage($page)->isPublished());
        self::assertNull(self::scheduledPublishOf($page));
    }

    /**
     * The builder schedules in PHP's default time zone, the one the overview page shows the time in, whatever the
     * editor's browser uses, and both name it
     *
     * @test
     */
    public function it_schedules_a_publish_in_the_builder_in_php_s_default_time_zone_whatever_the_browser_s_and_shows_it_unchanged_on_the_overview_page(): void
    {
        $this->useServerTimeZone('Europe/Copenhagen');
        self::useBrowserTimeZone('America/New_York');
        self::mockTime('2030-01-01 10:00:00');
        self::forgetSentMessages();
        $this->signInAsAdmin();
        $page = self::createPage('About us', 'about-us', 'Hello');
        $browser = $this->visit(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()));
        self::assertSame('America/New_York', $browser->evaluate('Intl.DateTimeFormat().resolvedOptions().timeZone'));

        $browser->getByRole('button', ['name' => 'Schedule…'])->click();
        $dialog = $browser->getByRole('dialog', ['name' => 'Schedule the publish']);
        Expect::locator($dialog->locator('input[type="datetime-local"]'))->toHaveAccessibleDescription('Europe/Copenhagen');
        $dialog->getByLabel('Publish at')->fill('2030-01-02T10:00');
        $dialog->getByRole('button', ['name' => 'Schedule the publish'])->click();

        Expect::locator($browser->getByText('Publishes 2030-01-02 10:00', ['exact' => true]))->toBeVisible();
        Expect::locator($browser->getByText('The draft is locked: it publishes 2030-01-02 10:00 (Europe/Copenhagen). Cancel the schedule to edit it.'))->toBeVisible();
        // 10:00 in Copenhagen is 09:00 in UTC
        self::assertSame('2030-01-02T09:00:00+00:00', self::scheduledPublishOf($page)?->getPublishAt()->format(\DATE_ATOM));

        $overview = $this->visit(sprintf('/admin/cms/entry/%s', $page->getUuid()));
        Expect::locator($overview->getByRole('region', ['name' => 'Publishing'])->locator('[data-scheduled-publish] .header'))->toHaveText('Publishes 2030-01-02 10:00 (Europe/Copenhagen)');
    }

    /**
     * Schedules the publish from the Publish button's menu, and waits until the builder is read-only
     */
    private function schedule(BrowserPage $browser, string $at): void
    {
        $browser->getByRole('button', ['name' => 'Schedule…'])->click();
        $dialog = $browser->getByRole('dialog', ['name' => 'Schedule the publish']);
        $dialog->getByLabel('Publish at')->fill($at);
        $dialog->getByRole('button', ['name' => 'Schedule the publish'])->click();
        Expect::locator($browser->getByRole('button', ['name' => 'Cancel the schedule']))->toBeVisible();
    }
}
