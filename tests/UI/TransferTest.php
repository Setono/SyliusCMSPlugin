<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\AssertionOptions;
use Playwright\Assertions\Expect;
use Playwright\Exception\PlaywrightException;
use Playwright\Network\ResponseInterface;
use Playwright\Page\PageInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Imports;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tests\Support\SentMessages;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * The Transfer screen exports: an admin user picks entries, editor presets and media folders, or everything, sees the
 * snippets and media items that come along because the picked records use them, and downloads the export as a zip. And
 * it imports: an admin user uploads an export zip, reviews the dry run's plan, and confirms it for the Messenger worker
 * to run. See "Transfer" in docs/spec/admin.md
 */
final class TransferTest extends UITestCase
{
    use Imports;
    use MediaItems;
    use SentMessages;
    use TemporaryDirectory;

    /** The committed export's picked page */
    private const PAGE = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

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
            $path = parse_url($response->url(), \PHP_URL_PATH);
            if (is_string($path)) {
                $responses[$path] = $response;
            }
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
     * @test
     */
    public function it_lets_an_admin_user_upload_an_export_zip_see_the_plan_with_a_conflict_overwrite_it_and_follow_the_result_to_an_imported_draft(): void
    {
        self::forgetSentMessages();
        $this->signInAsAdmin();
        // The export's page is here already, with a draft, so it's in conflict
        $page = self::createPageWithUuid(self::PAGE, 'Autumn sale (old)', 'autumn-sale');
        self::saveChanges($page, [['op' => 'set_entry_value', 'field' => 'title', 'locale' => 'en_US', 'value' => 'Autumn sale (work in progress)']]);

        $browser = $this->visit('/admin/cms/transfer');
        $import = $browser->getByRole('region', ['name' => 'Import']);
        $import->getByLabel('Export zip')->setInputFiles($this->zipOf(self::exportFixture()));
        $import->getByRole('button', ['name' => 'Upload'])->click();

        // The Messenger worker checks it with a dry run, and the page shows the plan once it has
        Expect::locator($browser->locator('[data-cms-import-progress]'))->toContainText('Waiting for the Messenger worker to check the export.');
        self::assertSame(1, self::consumeMessages());

        $conflicts = $browser->locator('[data-cms-import-conflicts]');
        Expect::locator($conflicts)->toContainText('Autumn sale has unpublished changes here.', new AssertionOptions(timeoutMs: 15_000));
        Expect::locator($browser->locator('[data-cms-import-entries] [data-cms-import-record]:has-text("Autumn sale")'))->toContainText('Update');
        self::assertStringContainsString('work in progress', (string) self::draftOf($page)?->getContent(), 'The dry run changed nothing');

        // Overwriting the conflict is required to import it
        $confirm = $browser->locator('form[data-cms-import-confirm]');
        $confirm->getByLabel('Overwrite the records in conflict, discarding their unpublished changes and cancelling their scheduled publishes')->check();
        $confirm->getByRole('button', ['name' => 'Import'])->click();

        Expect::locator($browser->locator('[data-cms-import-progress]'))->toContainText('Waiting for the Messenger worker to import it.');
        self::assertSame(1, self::consumeMessages());

        $result = $browser->locator('[data-cms-import-result]');
        Expect::locator($result)->toContainText('Imported: 2 entries, 1 editor preset, 3 media items and 2 media folders created, 1 entry updated.', new AssertionOptions(timeoutMs: 15_000));

        // The result links to the imported drafts to review and publish
        $result->getByRole('link', ['name' => 'Autumn sale', 'exact' => true])->click();

        Expect::locator($browser->locator('[data-overview-header]')->getByRole('heading', ['level' => 1]))->toContainText('Autumn sale');
        Expect::locator($browser->getByRole('region', ['name' => 'Publishing']))->toContainText('Unpublished changes.');
        self::assertStringContainsString('"title":"Autumn sale"', (string) self::draftOf(self::reloadPage($page))?->getContent());
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
