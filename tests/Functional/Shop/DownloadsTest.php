<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The Downloads block: a heading and a list of links to each document's permanent URL, named by the document's name.
 * See "Downloads" in docs/spec/blocks.md and "Permanent URLs" in docs/spec/media.md
 */
final class DownloadsTest extends WebTestCase
{
    use Entries;
    use MediaItems;

    private const BLOCK = '0193a5be-0000-7000-8000-0000000000a1';

    /**
     * @test
     */
    public function it_renders_a_heading_and_a_link_to_each_document_s_permanent_url_in_the_editor_s_order(): void
    {
        $client = self::createClient();
        $sizeGuide = $this->uploadMediaItem('Size guide.pdf', self::pdf('size guide'));
        $returns = $this->uploadMediaItem('returns-form.pdf', self::pdf('returns'));
        self::createPageWithBlocks('Help', 'help', [self::downloads([
            'heading' => ['en_US' => 'Guides & forms'],
            'documents' => [$returns->getUuid(), $sizeGuide->getUuid()],
        ])], published: true);

        $crawler = $client->request('GET', '/en_US/help');

        self::assertResponseIsSuccessful();
        $downloads = $crawler->filter('.cms-tree > div.cms-downloads');
        self::assertCount(1, $downloads);
        self::assertSame('Guides & forms', $downloads->filter('h2.cms-downloads__heading')->text());
        self::assertSame(
            [
                ['returns-form', sprintf('http://localhost/media/%s/returns-form.pdf', $returns->getUuid())],
                ['Size guide', sprintf('http://localhost/media/%s/size-guide.pdf', $sizeGuide->getUuid())],
            ],
            $downloads->filter('ul.cms-downloads__items > li > a.cms-downloads__item')->each(static fn (Crawler $link): array => [$link->text(), $link->attr('href')]),
        );
    }

    /**
     * @test
     */
    public function it_follows_the_link_to_the_document_s_current_file(): void
    {
        $client = self::createClient();
        $sizeGuide = $this->uploadMediaItem('size-guide.pdf', self::pdf());
        self::createPageWithBlocks('Help', 'help', [self::downloads(['documents' => [$sizeGuide->getUuid()]])], published: true);

        $href = (string) $client->request('GET', '/en_US/help')->filter('.cms-downloads__item')->attr('href');
        $client->request('GET', $href);

        self::assertResponseRedirects('/media/cms/' . $sizeGuide->getPath());
    }

    /**
     * @test
     */
    public function it_leaves_out_the_heading_when_it_is_empty_and_a_document_that_was_deleted(): void
    {
        $client = self::createClient();
        $sizeGuide = $this->uploadMediaItem('size-guide.pdf', self::pdf());
        self::createPageWithBlocks('Help', 'help', [self::downloads(['documents' => ['0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', $sizeGuide->getUuid()]])], published: true);

        $crawler = $client->request('GET', '/en_US/help');

        self::assertCount(0, $crawler->filter('.cms-downloads__heading'));
        self::assertSame(['size-guide'], $crawler->filter('.cms-downloads__item')->each(static fn (Crawler $link): string => $link->text()));
    }

    /**
     * @test
     */
    public function it_renders_nothing_without_documents(): void
    {
        $client = self::createClient();
        self::createPageWithBlocks('Help', 'help', [self::downloads(['heading' => ['en_US' => 'Guides']])], published: true);

        $client->request('GET', '/en_US/help');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.cms-downloads');
    }

    /**
     * @param array<string, mixed> $fields
     */
    private static function downloads(array $fields): StoredBlock
    {
        return new StoredBlock(self::BLOCK, 'downloads', 1, fields: $fields);
    }
}
