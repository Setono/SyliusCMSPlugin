<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Preview\SampleCandidatesInterface;
use Setono\SyliusCMSPlugin\Preview\SampleObject;
use Setono\SyliusCMSPlugin\Preview\SampleObjects;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Seo\CanonicalUrlGeneratorInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Support\SnippetPreviews;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\ProductAvailable;
use Setono\SyliusCMSPlugin\Tests\Unit\Preview\Fixture\EntryUuidIs;
use Setono\SyliusCMSPlugin\Tests\Unit\Preview\Fixture\ProductCodeIs;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\Taxon;

/**
 * The sample object a snippet's preview starts with: one that matches the placement's page scope, so the preview shows
 * the snippet where shoppers see it. See "Snippet preview" in docs/spec/builder.md
 */
final class SampleObjectsTest extends TestCase
{
    use ProphecyTrait;

    /** @var array<string, ProductInterface> */
    private static array $products = [];

    /**
     * @test
     */
    public function it_starts_with_a_sample_object_that_matches_the_page_scope(): void
    {
        $samples = $this->samples(['T_SHIRT', 'MUG', 'CAP']);

        $sample = $samples->starting('product', [self::codeIs('MUG')], Rendering::context());

        self::assertInstanceOf(ProductInterface::class, $sample);
        self::assertSame('MUG', $sample->getCode());
    }

    /**
     * @test
     */
    public function it_starts_with_the_first_candidate_when_the_scope_is_empty(): void
    {
        $sample = $this->samples(['T_SHIRT', 'MUG'])->starting('product', [], Rendering::context());

        self::assertInstanceOf(ProductInterface::class, $sample);
        self::assertSame('T_SHIRT', $sample->getCode());
    }

    /**
     * The preview then warns that it's outside the scope
     *
     * @test
     */
    public function it_starts_with_the_first_candidate_when_none_matches_the_page_scope(): void
    {
        $sample = $this->samples(['T_SHIRT', 'MUG'])->starting('product', [self::codeIs('JEANS')], Rendering::context());

        self::assertInstanceOf(ProductInterface::class, $sample);
        self::assertSame('T_SHIRT', $sample->getCode());
    }

    /**
     * A candidate the shopper can't see, such as a disabled product, has no page to show
     *
     * @test
     */
    public function it_leaves_out_candidates_that_don_t_load(): void
    {
        $sample = $this->samples(['DISABLED', 'MUG'], loaded: ['MUG'])->starting('product', [], Rendering::context());

        self::assertInstanceOf(ProductInterface::class, $sample);
        self::assertSame('MUG', $sample->getCode());
    }

    /**
     * For a layout's slot, an entry whose layout renders it, so the preview shows the slot on a real page. One that
     * also matches the page scope comes first
     *
     * @test
     */
    public function it_starts_with_a_sample_entry_whose_layout_renders_the_slot(): void
    {
        $standard = self::page('standard');
        $promo = self::page('promo');
        $alsoPromo = self::page('promo');
        $samples = $this->entrySamples([$standard, $promo, $alsoPromo]);
        $slot = new SlotMetadata('app.promo.after_blocks', 'label', ['entry']);

        self::assertSame($promo, $samples->starting('entry', [], Rendering::context(), $slot));
        self::assertSame($alsoPromo, $samples->starting('entry', [self::entryIs($alsoPromo)], Rendering::context(), $slot), 'One in the scope');
        self::assertSame($promo, $samples->starting('entry', [self::entryIs($standard)], Rendering::context(), $slot), 'One outside the scope, rather than one whose page doesn\'t show the slot');
        self::assertSame($standard, $samples->starting('entry', [], Rendering::context()), 'Without a slot, the first');
    }

    /**
     * The preview then shows it on a plain page
     *
     * @test
     */
    public function it_starts_with_the_first_candidate_when_no_candidate_s_page_shows_the_slot(): void
    {
        $standard = self::page('standard');

        self::assertSame($standard, $this->entrySamples([$standard, self::page(null)])->starting('entry', [], Rendering::context(), new SlotMetadata('app.promo.after_blocks', 'label', ['entry'])));
    }

    /**
     * @test
     */
    public function it_starts_with_none_when_there_is_no_candidate(): void
    {
        self::assertNull($this->samples([])->starting('product', [], Rendering::context()));
    }

    /**
     * The scope's own references load in the same pass, so a condition with a product parameter matches
     *
     * @test
     */
    public function it_loads_the_candidates_with_the_scope_s_references_in_one_pass(): void
    {
        $loader = $this->prophesize(ReferenceLoaderInterface::class);
        $loader->load(Argument::that(static function (iterable $references): bool {
            self::assertEquals([new Reference('product', 'T_SHIRT', false), new Reference('product', 'CAP', false)], [...$references]);

            return true;
        }), Argument::type(RenderContext::class))
            ->shouldBeCalledOnce()
            ->willReturn(new ReferenceMap(['product' => ['T_SHIRT' => self::product('T_SHIRT'), 'CAP' => self::product('CAP')]]));

        $sample = $this->samples(['T_SHIRT'], loader: $loader->reveal())->starting('product', [['kind' => 'product_available', 'negate' => false, 'params' => ['product' => 'CAP']]], Rendering::context());

        self::assertInstanceOf(ProductInterface::class, $sample);
        self::assertSame('T_SHIRT', $sample->getCode());
    }

    /**
     * @test
     */
    public function it_tells_whether_a_sample_object_matches_a_page_scope(): void
    {
        $samples = $this->samples([]);

        self::assertTrue($samples->matches([self::codeIs('MUG')], 'product', self::product('MUG'), Rendering::context()));
        self::assertFalse($samples->matches([self::codeIs('MUG')], 'product', self::product('T_SHIRT'), Rendering::context()));
        self::assertTrue($samples->matches([], 'product', self::product('T_SHIRT'), Rendering::context()), 'An empty scope matches every page');
        self::assertSame(
            ['MUG' => true, 'T_SHIRT' => false],
            $samples->matchesEach([self::codeIs('MUG')], 'product', ['MUG' => self::product('MUG'), 'T_SHIRT' => self::product('T_SHIRT')], Rendering::context()),
        );
    }

    /**
     * @test
     */
    public function it_loads_a_sample_object_as_the_shopper_sees_it(): void
    {
        $mug = self::product('MUG');

        self::assertSame($mug, $this->samples([])->load(new SampleObject('product', 'MUG'), Rendering::context()));
        self::assertNull($this->samples([], loaded: [])->load(new SampleObject('product', 'GONE'), Rendering::context()));
    }

    /**
     * Products, taxons and entries are what the builder searches and the shop loads by reference
     *
     * @test
     */
    public function it_takes_the_first_context_key_whose_objects_can_be_sample_objects(): void
    {
        self::assertSame('product', SampleObjects::keyOf(['product']));
        self::assertSame('taxon', SampleObjects::keyOf(['store_location', 'taxon', 'product']));
        self::assertNull(SampleObjects::keyOf(['store_location']));
        self::assertNull(SampleObjects::keyOf([]));
    }

    /**
     * @test
     */
    public function it_refers_to_products_and_taxons_by_code_and_to_entries_by_uuid(): void
    {
        $taxon = new Taxon();
        $taxon->setCode('SHOES');
        $page = new Page();

        self::assertEquals(new SampleObject('product', 'MUG'), SampleObjects::of('product', self::product('MUG')));
        self::assertEquals(new SampleObject('taxon', 'SHOES'), SampleObjects::of('taxon', $taxon));
        self::assertEquals(new SampleObject('entry', $page->getUuid()), SampleObjects::of('entry', $page));
        self::assertNull(SampleObjects::of('product', $taxon), 'Not an object of the key');
    }

    /**
     * @param list<string> $candidates the refs the candidates give, best first
     * @param list<string>|null $loaded the refs that load, or null for all of them
     */
    private function samples(array $candidates, ?array $loaded = null, ?ReferenceLoaderInterface $loader = null): SampleObjects
    {
        $source = $this->prophesize(SampleCandidatesInterface::class);
        $source->refs('product', Argument::cetera())->willReturn($candidates);

        if (null === $loader) {
            $records = [];
            foreach ($loaded ?? [...$candidates, 'MUG'] as $code) {
                $records[$code] = self::product($code);
            }

            $prophecy = $this->prophesize(ReferenceLoaderInterface::class);
            $prophecy->load(Argument::cetera())->willReturn(new ReferenceMap(['product' => $records]));
            $loader = $prophecy->reveal();
        }

        return new SampleObjects(
            $source->reveal(),
            $loader,
            Conditions::matcher(['product_code_is' => new ProductCodeIs(), 'product_available' => new ProductAvailable()]),
            SnippetPreviews::hostPageUrls(),
        );
    }

    /**
     * Sample entries, which all load, and whose pages are at their UUID
     *
     * @param list<Page> $candidates best first
     */
    private function entrySamples(array $candidates): SampleObjects
    {
        $source = $this->prophesize(SampleCandidatesInterface::class);
        $source->refs('entry', Argument::cetera())->willReturn(array_map(static fn (Page $page): string => $page->getUuid(), $candidates));

        $records = [];
        foreach ($candidates as $page) {
            $records[$page->getUuid()] = $page;
        }

        $loader = $this->prophesize(ReferenceLoaderInterface::class);
        $loader->load(Argument::cetera())->willReturn(new ReferenceMap(['entry' => $records]));

        $canonicalUrls = $this->prophesize(CanonicalUrlGeneratorInterface::class);
        $canonicalUrls->generate(Argument::cetera())->will(static function (array $arguments): string {
            $page = $arguments[0];
            self::assertInstanceOf(Page::class, $page);

            return 'https://shop.example/en_US/' . $page->getUuid();
        });

        return new SampleObjects(
            $source->reveal(),
            $loader->reveal(),
            Conditions::matcher(['entry_uuid_is' => new EntryUuidIs()]),
            SnippetPreviews::hostPageUrls($canonicalUrls->reveal()),
        );
    }

    /**
     * A page in the layout, or in the default one, standard, for null
     */
    private static function page(?string $layout): Page
    {
        $page = new Page();
        $page->setBlockTree(new StoredTree(null === $layout ? null : new StoredLayout($layout, 1), []));

        return $page;
    }

    /**
     * @return array<string, mixed>
     */
    private static function entryIs(Page $page): array
    {
        return ['kind' => 'entry_uuid_is', 'negate' => false, 'params' => ['uuid' => $page->getUuid()]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function codeIs(string $code): array
    {
        return ['kind' => 'product_code_is', 'negate' => false, 'params' => ['code' => $code]];
    }

    /**
     * The same product for the same code, so a test can tell which one it got
     */
    private static function product(string $code): ProductInterface
    {
        if (!isset(self::$products[$code])) {
            $product = new Product();
            $product->setCode($code);
            self::$products[$code] = $product;
        }

        return self::$products[$code];
    }
}
