<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;

/**
 * A snippet has no URL of its own, so Share draft on its overview page shows it on the host page of a placement the
 * editor picks, or on a plain page. See "Share links" in docs/spec/publishing.md and "Entry overview page" in
 * docs/spec/admin.md
 */
final class SnippetShareLinksTest extends UITestCase
{
    use Catalog;
    use Placements;

    /**
     * @test
     */
    public function it_lets_an_editor_share_a_snippet_s_draft_on_the_host_page_of_a_placement_or_on_a_plain_page(): void
    {
        self::createProduct('MUG', 'Mug');
        $snippet = self::createSnippet('Mug care', [self::heading('0193a5be-0000-7000-8000-0000000000a1', 'Rinse after use')], context: ['product']);
        $placement = self::place($snippet, 'product.after_price');
        $this->signInAsAdmin();

        // Share draft asks for the placement whose host page shows it, or a plain page
        $browser = $this->visit(sprintf('/admin/cms/entry/%s', $snippet->getUuid()));
        $browser->locator('[data-overview-header]')->getByRole('button', ['name' => 'Share draft'])->click();
        $dialog = $browser->getByRole('dialog', ['name' => 'Share draft']);
        Expect::locator($dialog->getByRole('combobox', ['name' => 'Shown on']))->toHaveValue($placement->getUuid());
        self::assertTexts(['Product page: under the price', 'A plain page'], $dialog->getByRole('combobox', ['name' => 'Shown on'])->locator('option'));
        $dialog->getByRole('button', ['name' => 'Create link'])->click();

        // The Share links section says where it shows, with the sample product a preview of the placement starts with
        Expect::locator($browser->locator('.sylius-flash-message'))->toContainText('The share link has been made.');
        $rows = $browser->getByRole('region', ['name' => 'Share links'])->locator('tbody tr');
        Expect::locator($rows)->toHaveCount(1);
        Expect::locator($rows->nth(0)->locator('[data-shown-on]'))->toHaveText('Product page: under the price · MUG');
        $url = $rows->nth(0)->getByRole('textbox', ['name' => 'Link'])->inputValue();
        self::assertMatchesRegularExpression('#^http://localhost/en_US/products/mug\?_cms_share=[A-Za-z0-9_-]{43}$#', $url);

        // A plain page
        $browser->locator('[data-overview-header]')->getByRole('button', ['name' => 'Share draft'])->click();
        $dialog->getByRole('combobox', ['name' => 'Shown on'])->selectOption('A plain page');
        $dialog->getByRole('button', ['name' => 'Create link'])->click();
        Expect::locator($rows)->toHaveCount(2);
        Expect::locator($rows->nth(0)->locator('[data-shown-on]'))->toHaveText('A plain page · MUG');
        $plainUrl = $rows->nth(0)->getByRole('textbox', ['name' => 'Link'])->inputValue();
        self::assertMatchesRegularExpression('#^http://localhost/en_US/_cms/snippet-preview\?_cms_share=[A-Za-z0-9_-]{43}$#', $plainUrl);

        // Someone who isn't signed in sees the draft in its slot on the mug's page, though it was never published
        $visitor = self::createPlaywrightClient();
        $shared = $visitor->visit(self::pathOf($url));
        Expect::locator($shared->locator('#sylius-product-name'))->toHaveText('Mug');
        Expect::locator($shared->locator('.cms-slot--product-after-price .cms-heading'))->toHaveText('Rinse after use');
        self::assertSame(0, $shared->locator('[data-cms-slot-preview]')->count(), 'A share link has no outline');

        // and on the plain page, with no placement to show it through
        $plain = $visitor->visit(self::pathOf($plainUrl));
        Expect::locator($plain->locator('.cms-slot .cms-heading'))->toHaveText('Rinse after use');
    }

    /**
     * playwright-symfony prefixes the paths it visits with its own base URL, which is the share link's host here
     */
    private static function pathOf(string $url): string
    {
        self::assertSame('localhost', parse_url($url, \PHP_URL_HOST));

        return sprintf('%s?%s', (string) parse_url($url, \PHP_URL_PATH), (string) parse_url($url, \PHP_URL_QUERY));
    }
}
