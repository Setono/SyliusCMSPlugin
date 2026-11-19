<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\Expect;
use Playwright\Exception\PlaywrightException;
use Playwright\Network\ResponseInterface;
use Playwright\Page\PageInterface;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * The Transfer screen exports: an admin user picks entries, editor presets and media folders, or everything, sees the
 * snippets and media items that come along because the picked records use them, and downloads the export as a zip.
 * See "Transfer" in docs/spec/admin.md
 */
final class TransferTest extends UITestCase
{
    use MediaItems;

    /**
     * @test
     */
    public function it_lets_an_admin_user_pick_a_page_see_the_snippet_and_the_image_it_uses_added_and_download_the_zip(): void
    {
        $image = $this->uploadMediaItem('autumn-banner.png', self::png());
        $image->setName('Autumn banner photo');
        self::entityManager()->flush();
        $snippet = self::createSnippet('Autumn banner', [
            new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'image', 1, fields: ['image' => $image->getUuid()]),
        ], published: true);
        $page = self::createPageWithBlocks('Autumn sale', 'autumn-sale', [
            new StoredBlock('0193a5be-0000-7000-8000-000000000002', 'snippet', 1, fields: ['snippet' => $snippet->getUuid()]),
        ], published: true);
        $this->signInAsAdmin();

        $browser = $this->visit('/admin/cms');
        $browser->locator('[data-hub-menu]')->getByRole('link', ['name' => 'Transfer'])->click();
        Expect::locator($browser->getByRole('heading', ['name' => 'Transfer', 'level' => 1]))->toBeVisible();

        $browser->getByLabel('Find entries')->fill('autumn sale');
        $browser->getByRole('button', ['name' => 'Search', 'exact' => true])->click();
        $browser->getByRole('button', ['name' => 'Add Autumn sale'])->click();

        // The page is picked, and the snippet it holds and the image the snippet holds come along
        self::assertTexts(['Autumn sale'], $browser->locator('[data-cms-export-picked] [data-cms-export-title]'));
        $export = $browser->getByRole('region', ['name' => 'In the export']);
        Expect::locator($export)->toContainText('2 entries, 0 editor presets and 1 media item');
        self::assertTexts(['Autumn banner', 'Autumn banner photo'], $export->locator('[data-cms-export-added] [data-cms-export-title]'));

        // Download gives the export as a zip
        $responses = [];
        $browser->events()->onResponse(static function (ResponseInterface $response) use (&$responses): void {
            $responses[parse_url($response->url(), \PHP_URL_PATH)] = $response;
        });

        try {
            $browser->getByRole('button', ['name' => 'Download'])->click();
        } catch (PlaywrightException $exception) {
            // A headless browser downloads a zip it's led to
            self::assertStringContainsString('Download is starting', $exception->getMessage());
        }

        self::awaitResponse($browser, $responses, '/admin/cms/transfer/export');
        $zip = $responses['/admin/cms/transfer/export'] ?? null;
        self::assertNotNull($zip, 'Download leads to the export');
        self::assertSame(200, $zip->status());
        self::assertSame('application/zip', $zip->headerValue('content-type'));
        self::assertMatchesRegularExpression('/^attachment; filename=cms-export-\d{8}-\d{6}\.zip$/', (string) $zip->headerValue('content-disposition'));
        self::assertStringContainsString(sprintf('entries[]=%s', $page->getUuid()), urldecode($zip->url()));
    }

    /**
     * Lets the page's events in until the response of this path has come in, or 10 seconds have passed. playwright-php
     * hands the test a page's events only while it waits for the answer to a command, and a click that leads to a
     * download leaves no page to wait for
     *
     * @param array<string, ResponseInterface> $responses by path, which the page's response listener fills
     */
    private static function awaitResponse(PageInterface $page, array &$responses, string $path): void
    {
        $until = microtime(true) + 10;
        while (!isset($responses[$path]) && microtime(true) < $until) {
            $page->evaluate('() => true');
            usleep(50_000);
        }
    }
}
