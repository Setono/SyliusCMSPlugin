<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Image;
use Setono\SyliusCMSPlugin\Block\Snippet as SnippetBlock;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\MediaHandler;
use Setono\SyliusCMSPlugin\Field\ProductHandler;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Field\SnippetHandler;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Rendering\BlockReferences;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceTypeLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Rendering\SnippetTreesReferenceLoader;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\ProductAvailable;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgraderInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Uid\Uuid;

final class SnippetTreesReferenceLoaderTest extends TestCase
{
    use ProphecyTrait;

    private const LOGO = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d01';

    private const BADGE = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d02';

    /** @var array<string, array<string, object>> what each fake type loader holds, by type, then by id */
    private array $records = [];

    /** @var array<string, list<list<string>>> the ids each type was loaded with, one list per query */
    private array $loaded = [];

    /**
     * @test
     */
    public function it_loads_the_references_of_the_trees_of_referenced_snippets_in_the_same_pass_as_the_tree_s_own(): void
    {
        $badge = $this->snippet(self::image(self::key(), self::BADGE), self::productNote(self::key(), 'MUG'));
        $footer = $this->snippet(self::image(self::key(), self::LOGO), self::snippetBlock(self::key(), $badge));
        $this->records = [
            'entry' => [$footer->getUuid() => $footer, $badge->getUuid() => $badge],
            'media_item' => [self::LOGO => self::object(), self::BADGE => self::object()],
            'product' => ['T_SHIRT' => self::object(), 'MUG' => self::object()],
        ];

        $references = $this->loader()->load([
            new Reference('product', 'T_SHIRT', false),
            new Reference('entry', $footer->getUuid(), true),
        ], Rendering::context());

        self::assertSame($footer, $references->get('entry', $footer->getUuid()));
        self::assertSame($badge, $references->get('entry', $badge->getUuid()));
        self::assertTrue($references->has('media_item', self::LOGO));
        self::assertTrue($references->has('media_item', self::BADGE));
        self::assertTrue($references->has('product', 'T_SHIRT'));
        self::assertTrue($references->has('product', 'MUG'), 'The product a condition in the snippet inside the snippet names');

        // A round of snippets per level, and then one query per type for everything else
        self::assertSame([[$footer->getUuid()], [$badge->getUuid()]], $this->loaded['entry']);
        self::assertSame([[self::LOGO, self::BADGE]], $this->loaded['media_item']);
        self::assertSame([['T_SHIRT', 'MUG']], $this->loaded['product']);
    }

    /**
     * @test
     */
    public function it_does_not_read_the_tree_of_a_snippet_the_shopper_may_not_see(): void
    {
        $unpublished = $this->snippet(self::image(self::key(), self::LOGO));
        $this->records = ['media_item' => [self::LOGO => self::object()]];

        $references = $this->loader()->load([new Reference('entry', $unpublished->getUuid(), true)], Rendering::context());

        self::assertNull($references->get('entry', $unpublished->getUuid()));
        self::assertArrayNotHasKey('media_item', $this->loaded);
    }

    /**
     * @test
     */
    public function it_loads_each_snippet_once_when_snippets_show_each_other(): void
    {
        $a = $this->snippet();
        $b = $this->snippet(self::snippetBlock(self::key(), $a));
        $a->setBlockTree(new StoredTree(null, [self::snippetBlock(self::key(), $b), self::snippetBlock(self::key(), $a)]));
        $this->records = ['entry' => [$a->getUuid() => $a, $b->getUuid() => $b]];

        $references = $this->loader()->load([new Reference('entry', $a->getUuid(), true)], Rendering::context());

        self::assertSame($a, $references->get('entry', $a->getUuid()));
        self::assertSame($b, $references->get('entry', $b->getUuid()));
        self::assertSame([[$a->getUuid()], [$b->getUuid()]], $this->loaded['entry']);
    }

    /**
     * @test
     */
    public function it_loads_the_entries_links_lead_to_with_the_other_references_and_never_reads_their_trees(): void
    {
        $footer = $this->snippet();
        $page = new Page();
        $page->setBlockTree(new StoredTree(null, [self::image(self::key(), self::LOGO)]));
        $this->records = ['entry' => [$footer->getUuid() => $footer, $page->getUuid() => $page]];

        $references = $this->loader()->load([
            new Reference('entry', $page->getUuid(), false),
            new Reference('entry', $footer->getUuid(), true),
        ], Rendering::context());

        self::assertSame($page, $references->get('entry', $page->getUuid()));
        self::assertSame($footer, $references->get('entry', $footer->getUuid()));
        self::assertSame([[$footer->getUuid()], [$page->getUuid()]], $this->loaded['entry']);
        self::assertArrayNotHasKey('media_item', $this->loaded);
    }

    /**
     * @test
     */
    public function it_loads_references_without_snippets_in_one_pass(): void
    {
        $this->records = ['media_item' => [self::LOGO => self::object()]];

        $references = $this->loader()->load([new Reference('media_item', self::LOGO, true)], Rendering::context());

        self::assertTrue($references->has('media_item', self::LOGO));
        self::assertSame(['media_item' => [[self::LOGO]]], $this->loaded);
    }

    private function loader(): SnippetTreesReferenceLoader
    {
        $handlers = new FieldHandlerLocator(new ServiceLocator([
            MediaHandler::class => static fn (): MediaHandler => new MediaHandler(),
            SnippetHandler::class => static fn (): SnippetHandler => new SnippetHandler(),
            ProductHandler::class => static fn (): ProductHandler => new ProductHandler(),
        ]));
        $blockTypes = new BlockTypeRegistry(['image' => Image::class, 'snippet' => SnippetBlock::class], new FieldReader());

        $upgrader = $this->prophesize(StoredDataUpgraderInterface::class);
        $upgrader->readBlockTree(Argument::type(EntryInterface::class))->will(static function (array $arguments): StoredTree {
            $entry = $arguments[0];
            self::assertInstanceOf(EntryInterface::class, $entry);

            return $entry->getBlockTree();
        });

        $typeLoaders = [];
        foreach (['entry', 'media_item', 'product'] as $type) {
            $typeLoaders[$type] = fn (): ReferenceTypeLoaderInterface => $this->typeLoader($type);
        }

        return new SnippetTreesReferenceLoader(
            new ReferenceLoader(new ServiceLocator($typeLoaders)),
            new BlockReferences(
                $blockTypes,
                Conditions::matcher(['product_available' => new ProductAvailable()]),
                new ReferenceFinder(new TreeValues($blockTypes, new LayoutRegistry([], new FieldReader())), $handlers),
            ),
            $upgrader->reveal(),
        );
    }

    /**
     * Loads the records of its type the test holds, and records the ids of each query
     */
    private function typeLoader(string $type): ReferenceTypeLoaderInterface
    {
        $test = $this;
        $loader = $this->prophesize(ReferenceTypeLoaderInterface::class);
        // Prophecy binds a callback to the double, so the test is reached through $test
        $loader->load(Argument::type('array'), Argument::type(RenderContext::class))->will(static function (array $arguments) use ($test, $type): array {
            /** @var list<string> $ids */
            $ids = $arguments[0];
            $test->loaded[$type][] = $ids;

            return array_intersect_key($test->records[$type] ?? [], array_flip($ids));
        });

        return $loader->reveal();
    }

    private function snippet(StoredBlock ...$blocks): SnippetInterface
    {
        $snippet = new Snippet();
        $snippet->setBlockTree(new StoredTree(null, array_values($blocks)));

        return $snippet;
    }

    private static function snippetBlock(string $key, SnippetInterface $snippet): StoredBlock
    {
        return new StoredBlock($key, 'snippet', 1, fields: ['snippet' => $snippet->getUuid()]);
    }

    private static function image(string $key, string $mediaItem): StoredBlock
    {
        return new StoredBlock($key, 'image', 1, fields: ['image' => $mediaItem]);
    }

    /**
     * An image block shown only while a product is available, whose condition names the product
     */
    private static function productNote(string $key, string $product): StoredBlock
    {
        $block = new StoredBlock($key, 'image', 1);
        $block->conditions = [['kind' => 'product_available', 'negate' => false, 'params' => ['product' => $product]]];

        return $block;
    }

    private static function key(): string
    {
        return Uuid::v7()->toRfc4122();
    }

    private static function object(): object
    {
        return new \stdClass();
    }
}
