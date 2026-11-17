<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\RichText;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\MediaHandler;
use Setono\SyliusCMSPlugin\Field\MediaListHandler;
use Setono\SyliusCMSPlugin\Field\RichTextHandler;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\MediaItem;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Kind\BrokenReference;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Rendering\ReferenceTypeLoaderInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Links;
use Setono\SyliusCMSPlugin\Tests\Unit\Tree\Fixture\Gallery;
use Setono\SyliusCMSPlugin\Tests\Unit\Tree\Fixture\Hero;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\Taxon;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\TranslatableMessage;

final class BrokenReferenceTest extends TestCase
{
    use ProphecyTrait;

    private const A = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const B = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    private const KEPT = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d01';

    private const DELETED = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d02';

    private const ALSO_DELETED = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d03';

    /**
     * @test
     */
    public function it_finds_each_reference_that_no_longer_resolves_as_an_error_naming_the_block_and_the_field(): void
    {
        $page = self::page(new StoredTree(new StoredLayout('hero', 1, ['image' => self::ALSO_DELETED]), [
            new StoredBlock(self::A, 'gallery', 1, fields: ['cover' => self::KEPT, 'images' => [self::KEPT, self::DELETED]], children: [
                new StoredBlock(self::B, 'gallery', 1, fields: ['poster' => ['en_US' => self::KEPT, 'da_DK' => self::DELETED]]),
            ]),
        ]));

        $mediaItems = $this->prophesize(ReferenceTypeLoaderInterface::class);
        $mediaItems->existing([self::ALSO_DELETED, self::KEPT, self::DELETED])->willReturn([self::KEPT])->shouldBeCalledOnce();

        self::assertEquals([
            new Problem(
                'broken_reference',
                Severity::Error,
                $page,
                new TranslatableMessage('setono_sylius_cms.problem.broken_layout_reference', ['%field%' => new TranslatableMessage('app.cms.field.image'), '%type%' => new TranslatableMessage('setono_sylius_cms.reference_type.media_item')]),
                Fix::inBuilder($page),
                null,
                'image',
            ),
            self::inBlock($page, self::A, 'images', 'Images', null),
            self::inBlock($page, self::B, 'poster', 'Poster', 'da_DK'),
        ], [...$this->kind($mediaItems->reveal())->findIn($page)]);
    }

    /**
     * @test
     */
    public function it_finds_nothing_when_every_reference_resolves_or_has_no_type_to_check(): void
    {
        $page = self::page(new StoredTree(null, [new StoredBlock(self::A, 'gallery', 1, fields: ['cover' => self::KEPT, 'heading' => ['en_US' => 'Hello']])]));

        $mediaItems = $this->prophesize(ReferenceTypeLoaderInterface::class);
        $mediaItems->existing([self::KEPT])->willReturn([self::KEPT]);

        self::assertSame([], [...$this->kind($mediaItems->reveal())->findIn($page)]);
    }

    /**
     * @test
     */
    public function it_checks_nothing_in_a_tree_without_references(): void
    {
        $mediaItems = $this->prophesize(ReferenceTypeLoaderInterface::class);
        $mediaItems->existing(Argument::cetera())->shouldNotBeCalled();

        self::assertSame([], [...$this->kind($mediaItems->reveal())->findIn(self::page(new StoredTree(null, [new StoredBlock(self::A, 'gallery', 1)])))]);
    }

    /**
     * A link in rich text that doesn't resolve still shows its text, as plain text
     *
     * @test
     */
    public function it_says_a_link_in_rich_text_that_leads_to_a_deleted_record_shows_as_plain_text(): void
    {
        $page = self::page(new StoredTree(null, [new StoredBlock(self::A, 'rich_text', 1, fields: ['content' => ['da_DK' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Waffles', 'marks' => [['type' => 'cmsLink', 'attrs' => ['target' => 'recipe', 'ref' => 'WAFFLES', 'anchor' => null]]]],
        ]]]]]])]));

        $products = $this->prophesize(ReferenceTypeLoaderInterface::class);
        $products->existing(['WAFFLES'])->willReturn([])->shouldBeCalledOnce();

        self::assertEquals([new Problem(
            'broken_reference',
            Severity::Error,
            $page,
            new TranslatableMessage('setono_sylius_cms.problem.broken_rich_text_reference', [
                '%field%' => new TranslatableMessage('setono_sylius_cms.field.rich_text.content'),
                '%block%' => new TranslatableMessage('setono_sylius_cms.block_type.rich_text'),
                '%type%' => new TranslatableMessage('setono_sylius_cms.reference_type.product'),
            ]),
            Fix::inBuilder($page, self::A, 'da_DK'),
            self::A,
            'content',
            'da_DK',
        )], [...$this->kind($this->prophesize(ReferenceTypeLoaderInterface::class)->reveal(), products: $products->reveal())->findIn($page)]);
    }

    /**
     * A media item written, such as one deleted, has the entries that may refer to it counted again
     *
     * @test
     */
    public function it_reads_media_items_and_finds_the_entries_that_may_refer_to_those_written(): void
    {
        $page = new Page();
        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findReferencing([self::KEPT, self::DELETED])->willReturn([$page]);

        self::assertContains(MediaItemInterface::class, BrokenReference::getReferencedClasses());
        self::assertSame([$page], [...$this->kind($this->prophesize(ReferenceTypeLoaderInterface::class)->reveal(), $entries->reveal())->findEntriesReferencing([
            self::mediaItem(self::KEPT),
            new \stdClass(),
            self::mediaItem(self::DELETED),
        ])]);
    }

    /**
     * An entry, product or taxon written, such as one deleted, has the entries whose links may lead to it counted again
     *
     * @test
     */
    public function it_reads_the_records_links_lead_to_and_finds_the_entries_that_may_refer_to_those_written(): void
    {
        $linked = new Page();
        $page = new Page();
        $product = new Product();
        $product->setCode('T_SHIRT');
        $taxon = new Taxon();
        $taxon->setCode('SHIRTS');
        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findReferencing([$linked->getUuid(), 'T_SHIRT', 'SHIRTS'])->willReturn([$page]);

        self::assertSame([MediaItemInterface::class, EntryInterface::class, ProductInterface::class, TaxonInterface::class], BrokenReference::getReferencedClasses());
        self::assertSame([$page], [...$this->kind($this->prophesize(ReferenceTypeLoaderInterface::class)->reveal(), $entries->reveal())->findEntriesReferencing([$linked, $product, $taxon])]);
    }

    /**
     * @test
     */
    public function it_finds_no_entry_without_a_media_item(): void
    {
        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findReferencing(Argument::cetera())->shouldNotBeCalled();

        self::assertSame([], [...$this->kind($this->prophesize(ReferenceTypeLoaderInterface::class)->reveal(), $entries->reveal())->findEntriesReferencing([new \stdClass()])]);
    }

    private static function inBlock(Page $page, string $key, string $field, string $label, ?string $locale): Problem
    {
        return new Problem(
            'broken_reference',
            Severity::Error,
            $page,
            new TranslatableMessage('setono_sylius_cms.problem.broken_reference', [
                '%field%' => new TranslatableMessage($label),
                '%block%' => new TranslatableMessage('app.cms.block_type.gallery'),
                '%type%' => new TranslatableMessage('setono_sylius_cms.reference_type.media_item'),
            ]),
            Fix::inBuilder($page, $key, $locale),
            $key,
            $field,
            $locale,
        );
    }

    private static function mediaItem(string $uuid): MediaItemInterface
    {
        $mediaItem = new MediaItem();
        (new \ReflectionProperty($mediaItem, 'uuid'))->setValue($mediaItem, $uuid);

        return $mediaItem;
    }

    private static function page(StoredTree $tree): Page
    {
        $page = new Page();
        $page->setBlockTree($tree);

        return $page;
    }

    private function kind(ReferenceTypeLoaderInterface $mediaItems, ?EntryRepositoryInterface $entries = null, ?ReferenceTypeLoaderInterface $products = null): BrokenReference
    {
        $blockTypes = new BlockTypeRegistry(['gallery' => Gallery::class, 'rich_text' => RichText::class], new FieldReader());
        $handlers = new FieldHandlerLocator(new ServiceLocator([
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            MediaHandler::class => static fn (): MediaHandler => new MediaHandler(),
            MediaListHandler::class => static fn (): MediaListHandler => new MediaListHandler(),
            RichTextHandler::class => Links::richTextHandler(...),
        ]));
        $products ??= $this->prophesize(ReferenceTypeLoaderInterface::class)->reveal();

        return new BrokenReference(
            new ReferenceFinder(new TreeValues($blockTypes, new LayoutRegistry(['page' => ['hero' => Hero::class]], new FieldReader())), $handlers),
            new ContentTypeRegistry(['page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page']]),
            $blockTypes,
            new LayoutRegistry(['page' => ['hero' => Hero::class]], new FieldReader()),
            new ServiceLocator([
                'media_item' => static fn (): ReferenceTypeLoaderInterface => $mediaItems,
                'product' => static fn (): ReferenceTypeLoaderInterface => $products,
            ]),
            $entries ?? $this->prophesize(EntryRepositoryInterface::class)->reveal(),
        );
    }
}
