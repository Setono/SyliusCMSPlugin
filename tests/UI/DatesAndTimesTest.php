<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Locator\LocatorInterface;
use Setono\SyliusCMSPlugin\Model\BlogPostInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Blog;
use Setono\SyliusCMSPlugin\Tests\Support\ServerTimeZone;

/**
 * Every date and time the builder reads or shows is in PHP's default time zone, whatever the editor's browser uses,
 * labelled with its name, and stored in UTC. The server is in Copenhagen and the browser in New York here. See "Dates
 * and times" in docs/spec/builder.md
 */
final class DatesAndTimesTest extends UITestCase
{
    use Blog;
    use ServerTimeZone;

    protected function setUp(): void
    {
        parent::setUp();

        // Before the kernel boots, as Twig keeps the time zone it first shows a date in
        $this->useServerTimeZone('Europe/Copenhagen');
        self::useBrowserTimeZone('America/New_York');
    }

    /**
     * @test
     */
    public function it_takes_a_date_and_time_field_in_php_s_default_time_zone_whatever_the_browser_s_and_the_storefront_shows_that_time(): void
    {
        $this->signInAsAdmin();
        $post = self::createBlogPost('Opening night', 'opening-night');
        $builder = sprintf('/admin/cms/entry/%s/builder', $post->getUuid());
        $browser = $this->visit($builder);
        self::assertSame('America/New_York', $browser->evaluate('Intl.DateTimeFormat().resolvedOptions().timeZone'));

        $date = self::input($browser->getByRole('tabpanel', ['name' => 'Settings']), 'Date');
        Expect::locator($date)->toHaveAccessibleDescription('Europe/Copenhagen');
        // Late in the evening in Copenhagen, which in New York's time would already be the next day in Copenhagen
        $date->fill('2026-01-02T22:00');
        Expect::locator($browser->getByRole('status'))->toHaveText('Saved');

        // It shows the same wall time again, with its zone
        $browser = $this->visit($builder);
        $date = self::input($browser->getByRole('tabpanel', ['name' => 'Settings']), 'Date');
        Expect::locator($date)->toHaveValue('2026-01-02T22:00');
        Expect::locator($date)->toHaveAccessibleDescription('Europe/Copenhagen');

        $browser->getByRole('button', ['name' => 'Publish', 'exact' => true])->click();
        Expect::locator($browser->getByText('Published', ['exact' => true]))->toBeVisible();

        // 22:00 in Copenhagen is 21:00 in UTC
        self::assertSame('2026-01-02T21:00:00+00:00', self::reloadBlogPost($post)->getPublishedAt()?->format(\DATE_ATOM));

        $shop = $this->visit('/en_US/blog/opening-night');
        Expect::locator($shop->locator('.cms-article__date'))->toHaveAttribute('datetime', '2026-01-02T22:00:00+01:00');
        Expect::locator($shop->locator('.cms-article__date'))->toHaveText('January 2, 2026');
    }

    /**
     * @test
     */
    public function it_takes_the_entrys_date_window_in_php_s_default_time_zone_whatever_the_browser_s_and_the_overview_page_shows_that_time(): void
    {
        $this->signInAsAdmin();
        $page = self::createPage('Christmas sale', 'christmas-sale', 'Hello', published: true);
        $builder = sprintf('/admin/cms/entry/%s/builder', $page->getUuid());
        $browser = $this->visit($builder);

        // The preview's Date is picked in it too
        Expect::locator($browser->getByLabel('Date', ['exact' => true]))->toHaveAccessibleDescription('Europe/Copenhagen');

        $showFrom = $browser->getByLabel('Show from', ['exact' => true]);
        Expect::locator($showFrom)->toHaveAccessibleDescription('Europe/Copenhagen');
        $showFrom->fill('2030-01-02T10:00');
        $showFrom->press('Enter');
        Expect::locator($browser->getByRole('complementary')->getByText('Saved', ['exact' => true]))->toBeVisible();

        // 10:00 in Copenhagen is 09:00 in UTC
        self::assertSame('2030-01-02T09:00:00+00:00', self::reloadPage($page)->getDateWindow()->from?->format(\DATE_ATOM));

        // It shows the same wall time again, with its zone
        $browser = $this->visit($builder);
        $showFrom = $browser->getByLabel('Show from', ['exact' => true]);
        Expect::locator($showFrom)->toHaveValue('2030-01-02T10:00');
        Expect::locator($showFrom)->toHaveAccessibleDescription('Europe/Copenhagen');

        $overview = $this->visit(sprintf('/admin/cms/entry/%s', $page->getUuid()));
        Expect::locator($overview->locator('[data-overview-header] [data-status]'))->toContainText('Shows from 2030-01-02 10:00');
    }

    /**
     * The input a label in the scope names. playwright-php's Locator::getByLabel() finds the label itself, which
     * Playwright's label engine follows to its input
     */
    private static function input(LocatorInterface $scope, string $label): LocatorInterface
    {
        return $scope->locator(sprintf('internal:label="%s"s', $label));
    }

    private static function reloadBlogPost(BlogPostInterface $post): BlogPostInterface
    {
        self::entityManager()->clear();
        $reloaded = self::entityManager()->getRepository($post::class)->findOneBy(['uuid' => $post->getUuid()]);
        self::assertInstanceOf(BlogPostInterface::class, $reloaded);

        return $reloaded;
    }
}
