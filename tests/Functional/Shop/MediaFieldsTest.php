<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\MediaFieldsKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tests\Support\ProfiledQueries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Crawler;

/**
 * A block type's Media and Media list fields store media item UUIDs, which load in one pass before the tree renders,
 * and templates read them with cms_value(). A media item that was deleted is a broken reference: rendering skips it,
 * and the admin flags it. See "References" in docs/spec/content-model.md and "Rendering a block tree" in
 * docs/spec/rendering.md
 */
final class MediaFieldsTest extends WebTestCase
{
    use Entries;
    use ProfiledQueries;
    use MediaItems;

    private const BLOCK = '0193a5be-0000-7000-8000-000000000001';

    private const OTHER_BLOCK = '0193a5be-0000-7000-8000-000000000002';

    protected static function getKernelClass(): string
    {
        return MediaFieldsKernel::class;
    }

    /**
     * @test
     */
    public function it_prints_the_names_of_the_media_items_a_block_holds_through_cms_value(): void
    {
        $client = self::createClient();
        $summer = $this->uploadMediaItem('summer.png', self::png());
        $beach = $this->uploadMediaItem('beach.jpg', self::jpeg());
        $winter = $this->uploadMediaItem('winter.png', self::png(rgb: [0, 0, 200]));
        self::createPageWithBlocks('Gallery', 'gallery', [self::mediaNames(self::BLOCK, $summer, [$winter, $beach])], published: true);

        $crawler = $client->request('GET', '/en_US/gallery');

        self::assertResponseIsSuccessful();
        self::assertSame('summer', $crawler->filter('.cms-tree .app-media-names__image')->text());
        self::assertSame(['winter', 'beach'], $crawler->filter('.cms-tree .app-media-names__gallery li')->each(static fn (Crawler $item): string => $item->text()));
        self::assertSame('2', $crawler->filter('.cms-tree .app-media-names__gallery')->attr('data-count'));
    }

    /**
     * @test
     */
    public function it_loads_every_media_item_of_the_page_with_one_query(): void
    {
        $client = self::createClient();
        $summer = $this->uploadMediaItem('summer.png', self::png());
        $beach = $this->uploadMediaItem('beach.jpg', self::jpeg());
        $winter = $this->uploadMediaItem('winter.png', self::png(rgb: [0, 0, 200]));
        self::createPageWithBlocks('Gallery', 'gallery', [
            self::mediaNames(self::BLOCK, $summer, [$winter, $beach]),
            self::mediaNames(self::OTHER_BLOCK, $beach, [$summer]),
        ], published: true);
        // The queries of a request are profiled from a kernel that's booted afresh, which comes after the first request
        $client->request('GET', '/');

        $client->enableProfiler();
        $client->request('GET', '/en_US/gallery');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.cms-tree .app-media-names:nth-child(2) .app-media-names__image', 'beach');
        self::assertCount(1, self::queriesOf($client, 'setono_sylius_cms__media_item '), 'One query loads the media items');
    }

    /**
     * @test
     */
    public function it_renders_a_deleted_media_item_as_an_empty_field_and_leaves_it_out_of_a_list(): void
    {
        $client = self::createClient();
        $summer = $this->uploadMediaItem('summer.png', self::png());
        $winter = $this->uploadMediaItem('winter.png', self::png(rgb: [0, 0, 200]));
        self::createPageWithBlocks('Gallery', 'gallery', [self::mediaNames(self::BLOCK, $summer, [$summer, $winter])], published: true);
        self::deleteMediaItem($summer);

        $crawler = $client->request('GET', '/en_US/gallery');

        self::assertResponseIsSuccessful();
        self::assertSame('No image', $crawler->filter('.cms-tree .app-media-names__image')->text());
        self::assertSame(['winter'], $crawler->filter('.cms-tree .app-media-names__gallery li')->each(static fn (Crawler $item): string => $item->text()));
    }

    /**
     * @test
     */
    public function it_flags_a_deleted_media_item_as_an_error_naming_the_block_and_the_field_on_the_overview_page(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $summer = $this->uploadMediaItem('summer.png', self::png());
        $page = self::createPageWithBlocks('Gallery', 'gallery', [self::mediaNames(self::BLOCK, $summer, [$summer])], published: true);
        self::assertEquals(new ProblemCount(), self::storedCount($page));

        self::deleteMediaItem($summer);

        self::assertEquals(new ProblemCount(2, 0), self::storedCount($page), 'Deleting the media item counts the entries that refer to it again');

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        self::assertResponseIsSuccessful();
        $problems = $crawler->filter('[data-section="problems"] [data-problem]');
        self::assertSame(
            [
                ['broken_reference', 'error', self::BLOCK, 'Image in the Media names block refers to a media item that was deleted, so it isn\'t shown.'],
                ['broken_reference', 'error', self::BLOCK, 'Gallery in the Media names block refers to a media item that was deleted, so it isn\'t shown.'],
            ],
            $problems->each(static fn (Crawler $problem): array => [
                $problem->attr('data-problem'),
                $problem->attr('data-severity'),
                $problem->attr('data-block'),
                $problem->filter('.description')->text(),
            ]),
        );
    }

    /**
     * @test
     */
    public function it_reports_a_deleted_media_item_as_a_broken_reference_in_setono_cms_lint(): void
    {
        $kernel = self::bootKernel();
        $summer = $this->uploadMediaItem('summer.png', self::png());
        $page = self::createPageWithBlocks('Gallery', 'gallery', [self::mediaNames(self::BLOCK, $summer, [])], published: true);

        $clean = new CommandTester((new Application($kernel))->find('setono:cms:lint'));
        $clean->execute([]);
        self::assertSame(Command::SUCCESS, $clean->getStatusCode());

        self::deleteMediaItem($summer);

        $tester = new CommandTester((new Application($kernel))->find('setono:cms:lint'));
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString(
            sprintf('%s published %s image Image in the Media names block refers to a media item that was deleted, so it isn\'t shown.', $page->getUuid(), self::BLOCK),
            (string) preg_replace('/\s+/', ' ', $tester->getDisplay()),
        );
    }

    /**
     * @param list<MediaItemInterface> $gallery
     */
    private static function mediaNames(string $key, MediaItemInterface $image, array $gallery): StoredBlock
    {
        return new StoredBlock($key, 'media_names', 1, fields: [
            'image' => $image->getUuid(),
            'gallery' => array_map(static fn (MediaItemInterface $mediaItem): string => $mediaItem->getUuid(), $gallery),
        ]);
    }

    private static function deleteMediaItem(MediaItemInterface $mediaItem): void
    {
        $manager = self::entityManager();
        $manager->remove($mediaItem);
        $manager->flush();
    }
}
