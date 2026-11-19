<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use League\Flysystem\UnableToReadFile;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tests\Support\TemporaryDirectory;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The Transfer screen's export: the picked records, and what came along because they use them, downloaded as a zip
 * holding the export's folder at its root. See "Transfer" in docs/spec/admin.md
 */
final class TransferTest extends WebTestCase
{
    use Entries;
    use MediaItems;
    use TemporaryDirectory;

    /**
     * @test
     */
    public function it_needs_an_admin_user(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/cms/transfer');
        self::assertResponseRedirects('http://localhost/admin/login');

        $client->request('GET', '/admin/cms/transfer/export?all=1');
        self::assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @test
     */
    public function it_lists_the_editor_presets_and_the_media_folders_to_pick_and_what_the_picked_ones_bring_along(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $campaigns = self::createMediaFolder('Campaigns');
        self::createMediaFolder('Autumn', $campaigns);
        $image = $this->uploadMediaItem('banner.png', self::png(), $campaigns);

        $crawler = $client->request('GET', sprintf('/admin/cms/transfer?folders[]=%s', $campaigns->getUuid()));

        self::assertResponseIsSuccessful();
        // Folders nest under their parents, and the picked one is checked
        self::assertSame(
            [['Campaigns', true], ['Autumn', false]],
            $crawler->filter('[data-cms-export-folders] .ui.checkbox')->each(static fn (Crawler $box): array => [trim($box->filter('label')->text()), null !== $box->filter('input')->attr('checked')]),
        );
        self::assertSame(['0 entries, 0 editor presets and 1 media item'], $crawler->filter('[data-cms-export-plan] [data-cms-export-counts]')->each(static fn (Crawler $node): string => trim($node->text())));
        self::assertCount(0, $crawler->filter('[data-cms-export-added]'));
    }

    /**
     * @test
     */
    public function it_finds_entries_to_add_marks_those_picked_already_and_leaves_out_the_one_a_remove_names(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $spring = self::createPage('Spring sale', 'spring-sale');
        $summer = self::createPage('Summer sale', 'summer-sale');
        $winter = self::createSnippet('Winter sale banner');

        $crawler = $client->request('GET', sprintf('/admin/cms/transfer?q=SALE&entries[]=%s&entries[]=%s&remove=%s', $spring->getUuid(), $summer->getUuid(), $spring->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertSame(['Summer sale'], $crawler->filter('[data-cms-export-picked] [data-cms-export-title]')->each(static fn (Crawler $title): string => $title->text()));
        // Newest first, snippets too, each with an Add, but the one picked already
        self::assertSame(
            [['Winter sale banner', 'Add Winter sale banner'], ['Summer sale', null], ['Spring sale', 'Add Spring sale']],
            $crawler->filter('[data-cms-export-found] tr')->each(static fn (Crawler $row): array => [$row->filter('[data-cms-export-title]')->text(), $row->filter('button')->count() > 0 ? $row->filter('button')->attr('aria-label') : null]),
        );
        self::assertSame($winter->getUuid(), $crawler->filter('[data-cms-export-found] button')->first()->attr('value'));
    }

    /**
     * @test
     */
    public function it_downloads_the_export_of_the_picked_records_as_a_zip_holding_the_export_at_its_root(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $image = $this->uploadMediaItem('banner.png', self::png());
        $snippet = self::createSnippet('Autumn banner', [new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'image', 1, fields: ['image' => $image->getUuid()])], published: true);
        $page = self::createPageWithBlocks('Autumn sale', 'autumn-sale', [new StoredBlock('0193a5be-0000-7000-8000-000000000002', 'snippet', 1, fields: ['snippet' => $snippet->getUuid()])], published: true);

        $client->request('GET', sprintf('/admin/cms/transfer/export?entries[]=%s', $page->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/zip');
        $file = $this->temporaryFile('export.zip', $client->getInternalResponse()->getContent());
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($file));
        $names = [];
        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $names[] = (string) $zip->getNameIndex($index);
        }

        sort($names);
        self::assertSame([
            sprintf('entries/%s.json', min($page->getUuid(), $snippet->getUuid())),
            sprintf('entries/%s.json', max($page->getUuid(), $snippet->getUuid())),
            'manifest.json',
            sprintf('media/%s.json', $image->getUuid()),
            sprintf('media/%s/banner.png', $image->getUuid()),
            'media/folders.json',
        ], $names);
        /** @var array{picked: array{entries: list<string>, media: list<string>}} $manifest */
        $manifest = CanonicalJson::decode((string) $zip->getFromName('manifest.json'));
        self::assertSame([$page->getUuid()], $manifest['picked']['entries']);
        self::assertSame([], $manifest['picked']['media']);
        $zip->close();
    }

    /**
     * A media file missing from the storage fails the export, which leaves no zip behind. ZipArchive writes what it has
     * once it's freed, unless it's discarded
     *
     * @test
     */
    public function it_leaves_no_zip_behind_when_the_export_fails(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $client->catchExceptions(false);
        $image = $this->uploadMediaItem('banner.png', self::png());
        $snippet = self::createSnippet('Autumn banner', [new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'image', 1, fields: ['image' => $image->getUuid()])], published: true);
        unlink(self::mediaDirectory() . '/' . $image->getPath());
        $before = self::exportZips();
        // As in production, so the exception's trace doesn't keep the zip alive after the request
        $ignoreArgs = ini_set('zend.exception_ignore_args', '1');

        try {
            $client->request('GET', sprintf('/admin/cms/transfer/export?entries[]=%s', $snippet->getUuid()));
            self::fail('The export fails');
        } catch (UnableToReadFile) {
        } finally {
            ini_set('zend.exception_ignore_args', (string) $ignoreArgs);
        }

        self::assertSame([], array_values(array_diff(self::exportZips(), $before)));
    }

    /**
     * @test
     */
    public function it_sends_the_admin_user_back_to_pick_something_when_nothing_is_picked(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        $client->request('GET', '/admin/cms/transfer/export');

        self::assertResponseRedirects('/admin/cms/transfer');
        $client->followRedirect();
        self::assertSelectorTextContains('.sylius-flash-message.negative', 'Pick what to export first.');

        // A parameter that isn't a list picks nothing
        $page = self::createPage('Autumn sale', 'autumn-sale', published: true);
        $client->request('GET', sprintf('/admin/cms/transfer/export?entries=%s', $page->getUuid()));

        self::assertResponseRedirects('/admin/cms/transfer');
    }

    /**
     * The zips exports write in the system's temporary directory until they're sent
     *
     * @return list<string>
     */
    private static function exportZips(): array
    {
        return glob(sys_get_temp_dir() . '/setono_sylius_cms_export_*.zip') ?: [];
    }
}
