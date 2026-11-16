<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Symfony\Client\PlaywrightKernelClient;

/**
 * Editors share an entry's draft from its overview page, as a link that shows it in the shop to anyone who has it,
 * without an admin account, until they revoke it. See "Share links" in docs/spec/publishing.md and "Entry overview page"
 * in docs/spec/admin.md
 */
final class ShareLinksTest extends UITestCase
{
    /**
     * @test
     */
    public function an_editor_shares_the_draft_with_someone_who_is_not_signed_in_and_revokes_the_link(): void
    {
        $this->signInAsAdmin();
        $page = self::createPage('About us', 'about-us', 'Hello');

        // Share draft asks for the channel and language, which is all there is here
        $browser = $this->visit(sprintf('/admin/cms/entry/%s', $page->getUuid()));
        $browser->locator('[data-overview-header]')->getByRole('button', ['name' => 'Share draft'])->click();
        $dialog = $browser->getByRole('dialog', ['name' => 'Share draft']);
        Expect::locator($dialog->getByLabel('Channel and language'))->toHaveValue('WEB|en_US');
        $dialog->getByRole('button', ['name' => 'Create link'])->click();

        // The Share links section lists it
        Expect::locator($browser->locator('.sylius-flash-message'))->toContainText('The share link has been made.');
        $rows = $browser->getByRole('region', ['name' => 'Share links'])->locator('tbody tr');
        Expect::locator($rows)->toHaveCount(1);
        $row = $rows->nth(0);
        self::assertSame('Guest', trim((string) $row->locator('[data-shown-to]')->textContent()));
        self::assertSame('Now', trim((string) $row->locator('[data-date]')->textContent()));
        self::assertSame('John Doe', trim((string) $row->locator('[data-created-by]')->textContent()));
        $url = $row->getByRole('textbox', ['name' => 'Link'])->inputValue();
        self::assertMatchesRegularExpression('#^http://localhost/en_US/about-us\?_cms_share=[A-Za-z0-9_-]{43}$#', $url);

        // Copy puts it on the clipboard
        self::getPlaywrightClient()->context()?->grantPermissions(['clipboard-read', 'clipboard-write']);
        $row->getByRole('button', ['name' => 'Copy'])->click();
        Expect::locator($row->getByRole('button', ['name' => 'Copied']))->toBeVisible();
        self::assertSame($url, $browser->evaluate('() => navigator.clipboard.readText()'));

        // Someone who isn't signed in sees the draft, read-only
        $visitor = self::createPlaywrightClient();
        $shared = $visitor->visit(self::pathOf($url));
        Expect::locator($shared->locator('.cms-tree .cms-heading'))->toHaveText('Hello');
        self::assertSame(200, self::statusCodeOf($visitor));
        self::assertSame(0, $shared->locator('script[data-cms-bridge]')->count(), 'A share link gets no bridge');

        // Revoking asks first
        $row->getByRole('button', ['name' => 'Revoke'])->click();
        $browser->locator('#confirmation-modal')->getByText('Yes')->click();
        Expect::locator($browser->locator('.sylius-flash-message'))->toContainText('The share link has been revoked.');
        Expect::locator($browser->getByRole('region', ['name' => 'Share links'])->locator('tbody tr'))->toHaveCount(0);

        // Then the link answers as it would for anyone else
        $gone = $visitor->visit(self::pathOf($url));
        self::assertSame(404, self::statusCodeOf($visitor));
        self::assertSame(0, $gone->locator('.cms-tree')->count());
    }

    private static function statusCodeOf(PlaywrightKernelClient $client): int
    {
        $response = $client->getLastSymfonyResponse();
        self::assertNotNull($response);

        return $response->getStatusCode();
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
