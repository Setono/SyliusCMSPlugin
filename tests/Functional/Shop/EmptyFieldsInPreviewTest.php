<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\MediaItems;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Every template prints its fields through cms_field(), so the builder can type them in place: the parts a block leaves
 * out in the shop while their field is empty are printed in the builder's preview, holding the empty field. See
 * "Block templates" in docs/spec/blocks.md
 */
final class EmptyFieldsInPreviewTest extends WebTestCase
{
    use Entries;
    use MediaItems;

    private const BANNER = '0193a5be-0000-7000-8000-0000000000e1';

    private const IMAGE = '0193a5be-0000-7000-8000-0000000000e2';

    private const LINK_LIST = '0193a5be-0000-7000-8000-0000000000e3';

    private const DOWNLOADS = '0193a5be-0000-7000-8000-0000000000e4';

    /**
     * @test
     */
    public function it_prints_the_empty_fields_a_block_leaves_out_in_the_shop_in_the_builder_s_preview(): void
    {
        $client = self::createClient();
        $page = $this->pageWithEmptyFields();

        $crawler = $client->request('GET', '/en_US/summer?_cms_preview=' . self::previewToken($page));

        self::assertSame(self::emptyField(self::BANNER, 'heading', 'text'), $crawler->filter('.cms-banner > .cms-banner__content > h2.cms-banner__heading')->html());
        self::assertSame(self::emptyField(self::BANNER, 'text', 'rich_text'), $crawler->filter('.cms-banner > .cms-banner__content > div.cms-banner__text')->html());
        self::assertCount(0, $crawler->filter('.cms-banner__buttons'), 'A banner without buttons has nothing to type there');
        self::assertSame(self::emptyField(self::IMAGE, 'caption', 'text'), $crawler->filter('.cms-image > figcaption.cms-image__caption')->html());
        self::assertSame(self::emptyField(self::LINK_LIST, 'heading', 'text'), $crawler->filter('.cms-link-list > h2.cms-link-list__heading')->html());
        self::assertNull($crawler->filter('.cms-link-list')->attr('aria-label'));
        self::assertSame(self::emptyField(self::DOWNLOADS, 'heading', 'text'), $crawler->filter('.cms-downloads > h2.cms-downloads__heading')->html());
    }

    /**
     * @test
     */
    public function it_leaves_them_out_in_the_shop(): void
    {
        $client = self::createClient();
        $this->pageWithEmptyFields();

        $crawler = $client->request('GET', '/en_US/summer');

        self::assertCount(0, $crawler->filter('.cms-banner__content'));
        self::assertCount(0, $crawler->filter('.cms-image__caption'));
        self::assertCount(0, $crawler->filter('.cms-link-list__heading'));
        self::assertCount(0, $crawler->filter('.cms-downloads__heading'));
        self::assertCount(1, $crawler->filter('.cms-downloads__item'));
        self::assertStringNotContainsString('cms:field', $crawler->filter('.cms-tree')->html());
    }

    private function pageWithEmptyFields(): EntryInterface
    {
        $image = $this->uploadMediaItem('summer-sale.jpg', self::jpeg(800, 400));
        $document = $this->uploadMediaItem('size-guide.pdf', self::pdf());

        return self::createPageWithBlocks('Summer', 'summer', [
            new StoredBlock(self::BANNER, 'banner', 1, fields: ['image' => $image->getUuid()]),
            new StoredBlock(self::IMAGE, 'image', 1, fields: ['image' => $image->getUuid()]),
            new StoredBlock(self::LINK_LIST, 'link_list', 1, children: [
                new StoredBlock('0193a5be-0000-7000-8000-0000000000e5', 'link', 1, fields: ['link' => ['target' => 'url', 'ref' => ['en_US' => 'https://example.com'], 'anchor' => null, 'label' => ['en_US' => 'Example']]]),
            ]),
            new StoredBlock(self::DOWNLOADS, 'downloads', 1, fields: ['documents' => [$document->getUuid()]]),
        ], published: true);
    }

    /**
     * The markers of an empty field the builder types in place
     */
    private static function emptyField(string $key, string $field, string $type): string
    {
        return sprintf('<!--cms:field %s %s %s--><!--/cms:field %1$s %2$s-->', $key, $field, $type);
    }
}
