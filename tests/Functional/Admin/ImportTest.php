<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use League\Flysystem\FilesystemOperator;
use Setono\SyliusCMSPlugin\Model\ImportInterface;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\ImportRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\HomepageClash;
use Setono\SyliusCMSPlugin\Tests\Support\Imports;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tests\Support\SentMessages;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
use Setono\SyliusCMSPlugin\Transfer\Import\AbandonImport;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportResult;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportResultNormalizer;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportState;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportStatus;
use Setono\SyliusCMSPlugin\Transfer\Import\RunImport;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

/**
 * The Transfer screen's import: the uploaded zip is kept in the plugin's transfer storage, the Messenger worker runs its
 * dry run, an admin user reviews the plan and confirms it, and the worker imports it and records its result, a refusal
 * included. See "Transfer" in docs/spec/admin.md and "Who runs it" in docs/spec/transfer.md
 */
final class ImportTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use Entries;
    use Imports;
    use MediaItems;
    use SentMessages;
    use TemporaryDirectory;

    private const PAGE = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    private const SNIPPET = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b';

    /**
     * @test
     */
    public function it_needs_an_admin_user(): void
    {
        $client = self::createClient();

        $client->request('POST', '/admin/cms/transfer/imports');
        self::assertResponseRedirects('http://localhost/admin/login');

        $client->request('GET', '/admin/cms/transfer/imports/0193a5be-0000-7000-8000-00000000ffff');
        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @test
     */
    public function it_keeps_an_uploaded_export_zip_in_the_plugins_storage_and_has_the_worker_check_it(): void
    {
        self::mockTime('2026-11-20 08:00:00');
        self::forgetSentMessages();
        $client = self::signedIn();
        $zip = $this->zipOf(self::exportFixture());

        $crawler = $this->upload($client, $zip);

        $import = self::latestImport();
        self::assertSame(ImportState::Checking, $import->getState());
        self::assertSame(basename($zip), $import->getFilename());
        self::assertResponseRedirects(sprintf('/admin/cms/transfer/imports/%s', $import->getUuid()));

        // In the transfer storage, not in a temporary file, so the worker reads it, and by default in a private directory
        // outside the web root, which the web server doesn't serve as it serves the media storage's
        $path = (string) $import->getPath();
        self::assertMatchesRegularExpression('#^imports/[0-9a-f]{32}\.zip$#', $path);
        self::assertSame(file_get_contents($zip), self::storage()->read($path));
        self::assertSame('private', self::storage()->visibility($path));
        $projectDirectory = self::projectDirectory();
        self::assertFileExists($projectDirectory . '/private/cms/' . $path);
        self::assertFileDoesNotExist($projectDirectory . '/public/media/cms/' . $path);
        $public = glob($projectDirectory . '/public/media/cms/imports/*');
        self::assertTrue(false === $public || [] === $public, 'The media storage holds no import');

        // The worker runs its dry run now, and abandons it in a day if no one confirms it
        $messages = self::sentMessages();
        self::assertCount(2, $messages);
        self::assertEquals(new RunImport($import->getUuid()), $messages[0]->getMessage());
        self::assertEquals(new AbandonImport($import->getUuid()), $messages[1]->getMessage());
        self::assertSame(86_400_000, $messages[1]->last(DelayStamp::class)?->getDelay());

        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSame('checking', $crawler->filter('[data-cms-import-state]')->attr('data-cms-import-state'));
        self::assertStringContainsString('Waiting for the Messenger worker to check the export.', $crawler->filter('[data-cms-import-progress]')->text());
    }

    /**
     * @test
     */
    public function it_refuses_a_file_that_is_not_an_export_zip_and_keeps_nothing(): void
    {
        self::forgetSentMessages();
        $client = self::signedIn();

        $this->upload($client, $this->temporaryFile('cms-export.zip', 'not a zip'));

        self::assertResponseRedirects('/admin/cms/transfer');
        $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.negative', 'cms-export.zip isn\'t an export zip. Upload a zip the Transfer screen or setono:cms:export made.');
        self::assertSame([], self::imports()->findLatest(1));
        self::assertSame([], self::sentMessages());

        $this->upload($client, $this->zipOf($this->temporaryDirectory()));
        self::assertResponseRedirects('/admin/cms/transfer');
        self::assertSame([], self::imports()->findLatest(1));
    }

    /**
     * A request over PHP's post_max_size arrives without its file
     *
     * @test
     */
    public function it_asks_for_an_export_zip_when_the_upload_has_no_file(): void
    {
        $client = self::signedIn();
        $crawler = $client->request('GET', '/admin/cms/transfer');
        $token = $crawler->filter('form[data-cms-import-upload] input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/admin/cms/transfer/imports', ['_csrf_token' => $token]);

        self::assertResponseRedirects('/admin/cms/transfer');
        $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.negative', 'Choose an export zip to upload, of at most');
        self::assertSame([], self::imports()->findLatest(1));
    }

    /**
     * @test
     */
    public function it_answers_the_screens_script_with_the_page_to_go_to_as_json(): void
    {
        $client = self::signedIn();
        $crawler = $client->request('GET', '/admin/cms/transfer');
        $token = $crawler->filter('form[data-cms-import-upload] input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/admin/cms/transfer/imports', ['_csrf_token' => $token], ['export' => self::zipUpload($this->zipOf(self::exportFixture()))], ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        self::assertSame(['url' => sprintf('/admin/cms/transfer/imports/%s', self::latestImport()->getUuid())], json_decode((string) $client->getResponse()->getContent(), true));

        $client->request('POST', '/admin/cms/transfer/imports', ['_csrf_token' => $token], ['export' => self::zipUpload($this->temporaryFile('cms-export.zip', 'not a zip'))], ['HTTP_ACCEPT' => 'application/json']);

        self::assertSame(['url' => '/admin/cms/transfer'], json_decode((string) $client->getResponse()->getContent(), true));
        $client->request('GET', '/admin/cms/transfer');
        self::assertSelectorTextContains('.sylius-flash-message.negative', 'cms-export.zip isn\'t an export zip.');
    }

    /**
     * @test
     */
    public function it_refuses_an_upload_without_the_screens_csrf_token(): void
    {
        $client = self::signedIn();

        $client->request('POST', '/admin/cms/transfer/imports', ['_csrf_token' => 'wrong'], ['export' => self::zipUpload($this->zipOf(self::exportFixture()))]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame([], self::imports()->findLatest(1));
    }

    /**
     * @test
     */
    public function it_shows_the_dry_run_plan_and_the_handler_applies_the_import_an_admin_user_confirms_and_records_its_result(): void
    {
        self::forgetSentMessages();
        $client = self::signedIn();
        $this->upload($client, $this->zipOf(self::exportFixture()));
        $import = self::latestImport();

        self::assertSame(1, self::consumeMessages());

        $crawler = $client->request('GET', sprintf('/admin/cms/transfer/imports/%s', $import->getUuid()));
        self::assertResponseIsSuccessful();
        self::assertSame('checked', $crawler->filter('[data-cms-import-state]')->attr('data-cms-import-state'));
        // In the order they're written: the snippets an entry shows before it
        self::assertSame(
            ['Newsletter signup Snippet Came along Create', 'Autumn banner Snippet Came along Create', 'Autumn sale Page Create'],
            self::rows($crawler->filter('[data-cms-import-entries] [data-cms-import-record]')),
        );
        self::assertSame(['Autumn hero Create'], self::rows($crawler->filter('[data-cms-import-presets] [data-cms-import-record]')));
        self::assertCount(3, $crawler->filter('[data-cms-import-media-items] [data-cms-import-record]'));
        self::assertCount(0, $crawler->filter('[data-cms-import-conflicts]'));
        // Nothing in conflict, so there's nothing to overwrite
        self::assertCount(0, $crawler->filter('input[name="overwrite"]'));
        self::assertNull(self::entries()->findOneByUuid(self::PAGE), 'The dry run changes nothing');

        $client->submit($crawler->filter('form[data-cms-import-confirm]')->form(['publish' => '1']));

        self::assertResponseRedirects(sprintf('/admin/cms/transfer/imports/%s', $import->getUuid()));
        $import = self::import($import->getUuid());
        self::assertSame(ImportState::Importing, $import->getState());
        self::assertTrue($import->isPublish());
        self::assertFalse($import->isOverwrite());
        $path = (string) $import->getPath();

        $crawler = $client->followRedirect();
        self::assertStringContainsString('Waiting for the Messenger worker to import it.', $crawler->filter('[data-cms-import-progress]')->text());

        self::assertSame(1, self::consumeMessages());

        $import = self::import($import->getUuid());
        self::assertSame(ImportState::Finished, $import->getState());
        self::assertSame(ImportStatus::Applied, self::resultOf($import)->status);
        self::assertFalse(self::storage()->fileExists($path), 'The zip is deleted once the import finishes');
        $page = self::entries()->findOneByUuid(self::PAGE);
        self::assertInstanceOf(PageInterface::class, $page);
        self::assertTrue($page->isPublished());

        $crawler = $client->request('GET', sprintf('/admin/cms/transfer/imports/%s', $import->getUuid()));
        self::assertSame('finished', $crawler->filter('[data-cms-import-state]')->attr('data-cms-import-state'));
        self::assertStringContainsString('Imported: 3 entries, 1 editor preset, 3 media items and 2 media folders created.', $crawler->filter('[data-cms-import-result]')->text());
        self::assertSame(
            [['Autumn banner', sprintf('/admin/cms/entry/%s', self::SNIPPET)], ['Autumn sale', sprintf('/admin/cms/entry/%s', self::PAGE)]],
            array_slice($crawler->filter('[data-cms-import-entries-written] a')->each(static fn (Crawler $link): array => [trim($link->text()), (string) $link->attr('href')]), 1),
        );
    }

    /**
     * @test
     */
    public function it_lists_the_conflicts_and_records_an_import_that_refuses_for_them_without_messenger_retrying_it(): void
    {
        self::forgetSentMessages();
        $client = self::signedIn();
        $page = self::createPageWithUuid(self::PAGE, 'Autumn sale (old)', 'autumn-sale');
        self::saveChanges($page, [['op' => 'set_entry_value', 'field' => 'title', 'locale' => 'en_US', 'value' => 'Autumn sale (work in progress)']]);
        $this->upload($client, $this->zipOf(self::exportFixture()));
        $import = self::latestImport();
        self::consumeMessages();

        // The dry run runs as if overwriting, so the plan shows the conflict, and the import can be confirmed over it
        $crawler = $client->request('GET', sprintf('/admin/cms/transfer/imports/%s', $import->getUuid()));
        self::assertSame('checked', $crawler->filter('[data-cms-import-state]')->attr('data-cms-import-state'));
        self::assertConflicts($crawler);
        self::assertNotNull($crawler->filter('input[name="overwrite"]')->attr('required'));

        // Confirmed without overwriting, as a request without the checkbox's check would be, it refuses
        $client->request('POST', sprintf('/admin/cms/transfer/imports/%s/confirm', $import->getUuid()), ['_csrf_token' => $crawler->filter('form[data-cms-import-confirm] input[name="_csrf_token"]')->attr('value')]);
        self::assertResponseRedirects();
        $path = (string) self::import($import->getUuid())->getPath();
        self::consumeMessages();

        $import = self::import($import->getUuid());
        self::assertSame(ImportState::Finished, $import->getState());
        $result = self::resultOf($import);
        self::assertSame(ImportStatus::Refused, $result->status);
        self::assertTrue(true === $result->plan?->isRefusedOnlyForConflictsOverwritingResolves());
        // Recorded and returned, so the worker acknowledged the message rather than retrying it
        self::assertCount(2, self::acknowledgedMessages());
        self::assertFalse(self::storage()->fileExists($path));
        self::assertStringContainsString('work in progress', (string) self::draftOf(self::page())?->getContent());

        $crawler = $client->request('GET', sprintf('/admin/cms/transfer/imports/%s', $import->getUuid()));
        self::assertStringContainsString('The import refused. Nothing changed.', $crawler->filter('[data-cms-import-result]')->text());
        self::assertConflicts($crawler);
    }

    /**
     * @test
     */
    public function it_finishes_an_import_whose_dry_run_refuses_for_a_reason_overwriting_does_not_fix_and_lists_why(): void
    {
        self::forgetSentMessages();
        $client = self::signedIn();
        self::createPage('Autumn sale here', 'autumn-sale', published: true, channels: ['WEB']);
        $this->upload($client, $this->zipOf(self::exportFixture()));
        $import = self::latestImport();
        $path = (string) $import->getPath();

        self::consumeMessages();

        $import = self::import($import->getUuid());
        self::assertSame(ImportState::Finished, $import->getState());
        self::assertFalse(self::storage()->fileExists($path));
        $crawler = $client->request('GET', sprintf('/admin/cms/transfer/imports/%s', $import->getUuid()));
        self::assertStringContainsString('The import refused. Nothing changed.', $crawler->filter('[data-cms-import-result]')->text());
        self::assertStringContainsString('Autumn sale would have the URL /en_US/autumn-sale, which Autumn sale here has in WEB.', $crawler->filter('[data-cms-import-conflicts]')->text());
        self::assertCount(0, $crawler->filter('form[data-cms-import-confirm]'));
    }

    /**
     * Another page becomes WEB's homepage while the dry run writes, after it found WEB without one, so the page's
     * homepage row clashes with it. The worker records the refusal, rather than failing on the entity manager the failed
     * flush closed
     *
     * @test
     */
    public function it_records_the_refusal_of_an_import_whose_page_another_page_just_took_a_channels_homepage_from(): void
    {
        self::forgetSentMessages();
        $client = self::signedIn();
        $other = self::createPage('Sale', 'sale', channels: ['WEB']);
        $export = $this->copyOfFixture();
        $file = sprintf('%s/entries/%s.json', $export, self::PAGE);
        file_put_contents($file, str_replace('"homepageOf": []', '"homepageOf": ["WEB"]', (string) file_get_contents($file)));
        $this->upload($client, $this->zipOf($export));
        $import = self::latestImport();
        HomepageClash::withAnotherPage(self::entityManager(), $other->getId());

        self::consumeMessages();

        $import = self::import($import->getUuid());
        self::assertSame(ImportState::Finished, $import->getState());
        self::assertSame(ImportStatus::Refused, self::resultOf($import)->status);
        self::assertNull(self::entries()->findOneByUuid(self::PAGE));
        $crawler = $client->request('GET', sprintf('/admin/cms/transfer/imports/%s', $import->getUuid()));
        self::assertStringContainsString('The import refused. Nothing changed.', $crawler->filter('[data-cms-import-result]')->text());
        self::assertStringContainsString(
            'The settings of Autumn sale can\'t be saved: Another page just became this channel\'s homepage. Choose the channel again to make this page its homepage instead.',
            $crawler->filter('[data-cms-import-reasons]')->text(),
        );
    }

    /**
     * @test
     */
    public function it_abandons_an_import_an_admin_user_cancels_and_deletes_its_zip(): void
    {
        self::forgetSentMessages();
        $client = self::signedIn();
        $this->upload($client, $this->zipOf(self::exportFixture()));
        $import = self::latestImport();
        $path = (string) $import->getPath();
        $crawler = $client->followRedirect();

        $client->submit($crawler->filter('form[data-cms-import-abandon]')->form());

        self::assertResponseRedirects('/admin/cms/transfer');
        $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.positive', 'The import has been cancelled, and its zip deleted.');
        self::assertSame(ImportState::Abandoned, self::import($import->getUuid())->getState());
        self::assertFalse(self::storage()->fileExists($path));

        // The worker leaves it as it is
        self::consumeMessages();
        self::assertSame(ImportState::Abandoned, self::import($import->getUuid())->getState());
        self::assertNull(self::entries()->findOneByUuid(self::PAGE));
    }

    /**
     * The worker or another admin user moved the import on after its page was shown
     *
     * @test
     */
    public function it_says_an_import_has_moved_on_when_its_page_confirms_or_cancels_it_too_late(): void
    {
        self::forgetSentMessages();
        $client = self::signedIn();
        $this->upload($client, $this->zipOf(self::exportFixture()));
        $import = self::latestImport();
        $crawler = $client->followRedirect();
        $abandon = $crawler->filter('form[data-cms-import-abandon]')->form();
        $token = $crawler->filter('form[data-cms-import-abandon] input[name="_csrf_token"]')->attr('value');

        // The worker hasn't checked it yet
        $client->request('POST', sprintf('/admin/cms/transfer/imports/%s/confirm', $import->getUuid()), ['_csrf_token' => $token, 'overwrite' => '1']);

        self::assertResponseRedirects(sprintf('/admin/cms/transfer/imports/%s', $import->getUuid()));
        $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.negative', 'This import has moved on since the page was shown.');
        self::assertSame(ImportState::Checking, self::import($import->getUuid())->getState());

        $client->submit($abandon);
        $client->submit($abandon);

        self::assertResponseRedirects(sprintf('/admin/cms/transfer/imports/%s', $import->getUuid()));
        $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.negative', 'This import has moved on since the page was shown.');
        self::assertSame(ImportState::Abandoned, self::import($import->getUuid())->getState());
    }

    /**
     * The handler kept throwing an unexpected error until Messenger gave up retrying, which the worker reports with
     * WorkerMessageFailedEvent
     *
     * @test
     */
    public function it_fails_an_import_messenger_gave_up_on_shows_why_and_deletes_its_zip(): void
    {
        self::forgetSentMessages();
        $client = self::signedIn();
        $this->upload($client, $this->zipOf(self::exportFixture()));
        $import = self::latestImport();
        $path = (string) $import->getPath();

        // Retried as often as the transport allows
        $envelope = new Envelope(new RunImport($import->getUuid()), [new RedeliveryStamp(100)]);
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        $eventDispatcher->dispatch(new WorkerMessageFailedEvent($envelope, 'main', new HandlerFailedException($envelope, [new \RuntimeException('The database went away')])));

        self::assertSame(ImportState::Failed, self::import($import->getUuid())->getState());
        self::assertFalse(self::storage()->fileExists($path));

        $crawler = $client->request('GET', sprintf('/admin/cms/transfer/imports/%s', $import->getUuid()));
        self::assertResponseIsSuccessful();
        self::assertSame('failed', $crawler->filter('[data-cms-import-state]')->attr('data-cms-import-state'));
        self::assertCount(0, $crawler->filter('[data-cms-import-progress]'));
        self::assertSame('The import failed with an unexpected error, and the Messenger worker stopped retrying it: The database went away', $crawler->filter('[data-cms-import-failure]')->text());
    }

    /**
     * @test
     */
    public function it_abandons_an_import_no_one_confirms_within_a_day(): void
    {
        self::mockTime('2026-11-20 08:00:00');
        self::forgetSentMessages();
        $client = self::signedIn();
        $this->upload($client, $this->zipOf(self::exportFixture()));
        $import = self::latestImport();
        $path = (string) $import->getPath();
        self::assertSame(1, self::consumeMessages());
        self::assertSame(ImportState::Checked, self::import($import->getUuid())->getState());

        self::mockTime('2026-11-21 08:00:01');
        self::assertSame(1, self::consumeMessages());

        self::assertSame(ImportState::Abandoned, self::import($import->getUuid())->getState());
        self::assertFalse(self::storage()->fileExists($path));
    }

    /**
     * @test
     */
    public function it_lists_the_latest_imports_on_the_transfer_screen(): void
    {
        $client = self::signedIn();
        $this->upload($client, $this->zipOf(self::exportFixture()));
        $import = self::latestImport();

        $crawler = $client->request('GET', '/admin/cms/transfer');

        self::assertSame(
            [[$import->getFilename(), sprintf('/admin/cms/transfer/imports/%s', $import->getUuid())]],
            $crawler->filter('[data-cms-imports] a')->each(static fn (Crawler $link): array => [trim($link->text()), (string) $link->attr('href')]),
        );
    }

    /**
     * The conflicts of the committed export's page, made here after the export and edited since
     */
    private static function assertConflicts(Crawler $crawler): void
    {
        $conflicts = [];
        foreach ($crawler->filter('[data-cms-import-conflicts] li') as $item) {
            $conflicts[] = trim($item->textContent);
        }

        self::assertCount(2, $conflicts);
        self::assertSame('Autumn sale has unpublished changes here.', $conflicts[0]);
        self::assertMatchesRegularExpression('/^Autumn sale changed here at [0-9T:Z-]+, after the export\'s copy from 2026-11-12T10:15:00Z\.$/', $conflicts[1]);
    }

    private function upload(KernelBrowser $client, string $zip): Crawler
    {
        $crawler = $client->request('GET', '/admin/cms/transfer');
        $form = $crawler->filter('form[data-cms-import-upload]')->form();
        $field = $form['export'];
        self::assertInstanceOf(FileFormField::class, $field);
        $field->upload($zip);

        return $client->submit($form);
    }

    private static function zipUpload(string $zip): UploadedFile
    {
        return new UploadedFile($zip, basename($zip), 'application/zip', null, true);
    }

    private static function signedIn(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
    }

    private static function latestImport(): ImportInterface
    {
        self::entityManager()->clear();
        $imports = self::imports()->findLatest(1);
        self::assertCount(1, $imports);

        return $imports[0];
    }

    private static function import(string $uuid): ImportInterface
    {
        self::entityManager()->clear();
        $import = self::imports()->findOneByUuid($uuid);
        self::assertNotNull($import);

        return $import;
    }

    private static function resultOf(ImportInterface $import): ImportResult
    {
        return (new ImportResultNormalizer())->denormalize($import->getResult() ?? []);
    }

    private static function page(): PageInterface
    {
        $page = self::entries()->findOneByUuid(self::PAGE);
        self::assertInstanceOf(PageInterface::class, $page);

        return $page;
    }

    private static function imports(): ImportRepositoryInterface
    {
        /** @var ImportRepositoryInterface $imports */
        $imports = self::getContainer()->get(ImportRepositoryInterface::class);

        return $imports;
    }

    private static function entries(): EntryRepositoryInterface
    {
        self::entityManager()->clear();

        /** @var EntryRepositoryInterface $entries */
        $entries = self::getContainer()->get(EntryRepositoryInterface::class);

        return $entries;
    }

    private static function storage(): FilesystemOperator
    {
        /** @var FilesystemOperator $storage */
        $storage = self::getContainer()->get('setono_sylius_cms.transfer_storage');

        return $storage;
    }

    private static function projectDirectory(): string
    {
        $directory = self::getContainer()->getParameter('kernel.project_dir');
        \assert(is_string($directory));

        return $directory;
    }

    /**
     * Each row's text, with its whitespace squashed
     *
     * @return array<mixed>
     */
    private static function rows(Crawler $rows): array
    {
        return $rows->each(static fn (Crawler $row): string => trim((string) preg_replace('/\s+/', ' ', $row->text())));
    }
}
