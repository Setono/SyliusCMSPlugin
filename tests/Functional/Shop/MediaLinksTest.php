<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Repository\MediaUsageRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The media link target: a link to a document or video leads to its permanent URL, named by the media item's name, in
 * a Link field and in rich text alike, and the usage index counts it as a use. See "Link targets" in
 * docs/spec/extending.md and "Permanent URLs" in docs/spec/media.md
 */
final class MediaLinksTest extends WebTestCase
{
    use Entries;
    use MediaItems;

    private const BUTTON = '0193a5be-0000-7000-8000-0000000000d1';

    private const RICH_TEXT = '0193a5be-0000-7000-8000-0000000000d2';

    /**
     * @test
     */
    public function it_shows_a_button_named_by_the_document_that_leads_to_its_permanent_url(): void
    {
        $client = self::createClient();
        $document = $this->uploadMediaItem('size-guide.pdf', self::pdf());
        self::createPageWithBlocks('Sizes', 'sizes', [
            new StoredBlock(self::BUTTON, 'button', 1, fields: ['link' => ['target' => 'media', 'ref' => $document->getUuid(), 'anchor' => null, 'label' => []]]),
        ], published: true);

        $crawler = $client->request('GET', '/en_US/sizes');

        $button = $crawler->filter('.cms-tree > a.cms-button');
        self::assertSame('size-guide', $button->text());
        self::assertSame(sprintf('http://localhost/media/%s/size-guide.pdf', $document->getUuid()), $button->attr('href'));
    }

    /**
     * @test
     */
    public function it_links_rich_text_to_a_video_s_permanent_url(): void
    {
        $client = self::createClient();
        $video = $this->uploadMediaItem('shop-tour.mp4', self::mp4());
        self::createPageWithBlocks('Tour', 'tour', [new StoredBlock(self::RICH_TEXT, 'rich_text', 1, fields: ['content' => ['en_US' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Take the tour', 'marks' => [['type' => 'cmsLink', 'attrs' => ['target' => 'media', 'ref' => $video->getUuid(), 'anchor' => null]]]],
        ]]]]]])], published: true);

        $crawler = $client->request('GET', '/en_US/tour');

        self::assertSame(
            sprintf('<p><a href="http://localhost/media/%s/shop-tour.mp4">Take the tour</a></p>', $video->getUuid()),
            $crawler->filter('.cms-tree > .cms-rich-text p')->outerHtml(),
        );
    }

    /**
     * @test
     */
    public function it_renders_no_button_for_a_link_to_an_image_which_has_no_permanent_url(): void
    {
        $client = self::createClient();
        $image = $this->uploadMediaItem('summer-sale.png', self::png());
        self::createPageWithBlocks('Sale', 'sale', [
            new StoredBlock(self::BUTTON, 'button', 1, fields: ['link' => ['target' => 'media', 'ref' => $image->getUuid(), 'anchor' => null, 'label' => ['en_US' => 'See it']]]),
        ], published: true);

        $client->request('GET', '/en_US/sale');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.cms-tree .cms-button');
    }

    /**
     * @test
     */
    public function it_counts_a_link_to_a_document_as_a_use_of_it(): void
    {
        self::createClient();
        $document = $this->uploadMediaItem('size-guide.pdf', self::pdf());
        $page = self::createPageWithBlocks('Sizes', 'sizes', [
            new StoredBlock(self::BUTTON, 'button', 1, fields: ['link' => ['target' => 'media', 'ref' => $document->getUuid(), 'anchor' => null, 'label' => []]]),
        ], published: true);

        /** @var MediaUsageRepositoryInterface $usage */
        $usage = self::getContainer()->get(MediaUsageRepositoryInterface::class);

        self::assertSame([$document->getUuid() => 1], $usage->countByMediaItems([$document->getUuid()]));
        self::assertSame($page->getUuid(), $usage->findByMediaItem($document->getUuid())[0]->getEntry()?->getUuid());
    }
}
