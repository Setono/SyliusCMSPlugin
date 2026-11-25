<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\UI;

use Playwright\Assertions\AssertionOptions;
use Playwright\Assertions\Expect;
use Playwright\Exception\PlaywrightException;
use Playwright\Network\ResponseInterface;
use Playwright\Page\PageInterface;
use Setono\SyliusCMSPlugin\Model\ImportInterface;
use Setono\SyliusCMSPlugin\Repository\ImportRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Imports;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tests\Support\SentMessages;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
use Setono\SyliusCMSPlugin\Tests\Support\ZipHeaders;
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
    use ZipHeaders;

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
     * The zip's directory says its video is 2 GB, so it unpacks to more than transfer.max_unpacked_size's 1 GB, and the
     * Messenger worker's dry run refuses it before extracting anything. See "Media files" in docs/spec/transfer.md
     *
     * @test
     */
    public function it_shows_an_admin_user_why_an_import_of_a_zip_that_unpacks_to_more_than_an_import_takes_refused(): void
    {
        self::forgetSentMessages();
        $this->signInAsAdmin();
        $zip = $this->zipOf(self::exportFixture());
        self::declareSize($zip, 'media/0193a5be-70ab-7c12-8e2d-1b2c3d4e5f61/lookbook.mp4', 2 * 1024 * 1024 * 1024);

        $browser = $this->visit('/admin/cms/transfer');
        $import = $browser->getByRole('region', ['name' => 'Import']);
        $import->getByLabel('Export zip')->setInputFiles($zip);
        $import->getByRole('button', ['name' => 'Upload'])->click();

        Expect::locator($browser->locator('[data-cms-import-progress]'))->toContainText('Waiting for the Messenger worker to check the export.');
        self::assertSame(1, self::consumeMessages());

        Expect::locator($browser->locator('[data-cms-import-result]'))->toContainText('The import refused. Nothing changed.', new AssertionOptions(timeoutMs: 15_000));
        Expect::locator($browser->locator('[data-cms-import-reasons]'))->toContainText('The export zip unpacks to 2 GB, over the import limit of 1 GB.');
        // There's nothing to confirm
        Expect::locator($browser->locator('form[data-cms-import-confirm]'))->toHaveCount(0);
    }

    /**
     * The zip's directory says the page's JSON file is 20 MB, over the 16 MB an import reads of each JSON file, though
     * the zip is under transfer.max_unpacked_size, and the Messenger worker's dry run refuses it, naming the file, before
     * reading it. See "Media files" in docs/spec/transfer.md
     *
     * @test
     */
    public function it_shows_an_admin_user_why_an_import_of_an_export_with_a_json_file_over_the_cap_refused(): void
    {
        self::forgetSentMessages();
        $this->signInAsAdmin();
        $zip = $this->zipOf(self::exportFixture());
        self::declareSize($zip, 'entries/' . self::PAGE . '.json', 20 * 1024 * 1024);

        $browser = $this->visit('/admin/cms/transfer');
        $import = $browser->getByRole('region', ['name' => 'Import']);
        $import->getByLabel('Export zip')->setInputFiles($zip);
        $import->getByRole('button', ['name' => 'Upload'])->click();

        Expect::locator($browser->locator('[data-cms-import-progress]'))->toContainText('Waiting for the Messenger worker to check the export.');
        self::assertSame(1, self::consumeMessages());

        Expect::locator($browser->locator('[data-cms-import-result]'))->toContainText('The import refused. Nothing changed.', new AssertionOptions(timeoutMs: 15_000));
        Expect::locator($browser->locator('[data-cms-import-reasons]'))->toContainText('The export\'s file entries/' . self::PAGE . '.json is over the import limit of 16 MB per JSON file.');
        Expect::locator($browser->locator('form[data-cms-import-confirm]'))->toHaveCount(0);
    }

    /**
     * The Messenger worker stopped while checking the export, as when PHP runs out of memory, which skips Messenger's
     * retries, and gets its message again. See "Who runs it" in docs/spec/transfer.md
     *
     * @test
     */
    public function it_shows_an_admin_user_that_the_worker_stopped_while_working_on_an_import_which_is_not_run_again(): void
    {
        self::forgetSentMessages();
        $this->signInAsAdmin();

        $browser = $this->visit('/admin/cms/transfer');
        $import = $browser->getByRole('region', ['name' => 'Import']);
        $import->getByLabel('Export zip')->setInputFiles($this->zipOf(self::exportFixture()));
        $import->getByRole('button', ['name' => 'Upload'])->click();
        Expect::locator($browser->locator('[data-cms-import-progress]'))->toContainText('Waiting for the Messenger worker to check the export.');

        // The worker started checking it, and stopped before it recorded how that ended
        self::latestImport()->start(new \DateTimeImmutable());
        self::entityManager()->flush();
        self::assertSame(1, self::consumeMessages());

        Expect::locator($browser->locator('[data-cms-import-failure]'))->toHaveText('The Messenger worker stopped while it was working on this import, as when PHP runs out of memory or the worker is killed, so it isn\'t run again.', new AssertionOptions(timeoutMs: 15_000));
        Expect::locator($browser->getByText('Its zip has been deleted. Upload the export again to try once more, and look in the worker\'s logs for why it stopped.'))->toBeVisible();
        Expect::locator($browser->locator('[data-cms-import-entries], form[data-cms-import-confirm]'))->toHaveCount(0);
    }

    private static function latestImport(): ImportInterface
    {
        self::entityManager()->clear();

        /** @var ImportRepositoryInterface $imports */
        $imports = self::getContainer()->get(ImportRepositoryInterface::class);
        $latest = $imports->findLatest(1);
        self::assertCount(1, $latest);

        return $latest[0];
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
