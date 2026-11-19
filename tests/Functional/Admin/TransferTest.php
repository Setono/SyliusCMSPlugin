<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

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
        self::assertSame(0, $crawler->filter('[data-cms-export-added]')->count());
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
    }
}
