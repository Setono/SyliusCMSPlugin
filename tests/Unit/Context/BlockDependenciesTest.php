<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Block\Column;
use Setono\SyliusCMSPlugin\Block\Columns;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Context\BlockDependencies;
use Setono\SyliusCMSPlugin\Context\ContextDependency;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\ProductBadge;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A block whose type needs context keys depends on the entry providing them, wherever it sits in the tree.
 * See "Blocks and nesting" in docs/spec/content-model.md
 */
final class BlockDependenciesTest extends TestCase
{
    private const OUTER = '0193a5be-0000-7000-8000-000000000001';

    private const NESTED = '0193a5be-0000-7000-8000-000000000002';

    /**
     * @test
     */
    public function it_finds_every_block_whose_type_needs_context_keys_with_its_key(): void
    {
        $column = new StoredBlock('0193a5be-0000-7000-8000-000000000003', 'column', 1);
        $column->children = [new StoredBlock(self::NESTED, 'product_badge', 1), new StoredBlock('0193a5be-0000-7000-8000-000000000004', 'heading', 1)];
        $columns = new StoredBlock('0193a5be-0000-7000-8000-000000000005', 'columns', 1);
        $columns->children = [$column];
        // A block of a type the code no longer has needs nothing, but what's inside it may
        $gone = new StoredBlock('0193a5be-0000-7000-8000-000000000006', 'gone', 1);
        $gone->children = [new StoredBlock(self::OUTER, 'product_badge', 1)];
        $page = new Page();
        $page->setBlockTree(new StoredTree(null, [$columns, $gone]));

        $blockTypes = new BlockTypeRegistry([
            'columns' => Columns::class,
            'column' => Column::class,
            'heading' => Heading::class,
            'product_badge' => ProductBadge::class,
        ], new FieldReader());
        // Named by its block type's label
        $name = new TranslatableMessage('setono_sylius_cms.context.block', ['%block%' => new TranslatableMessage($blockTypes->get('product_badge')->label)], 'validators');

        self::assertEquals([
            new ContextDependency($name, ['product'], block: self::NESTED),
            new ContextDependency($name, ['product'], block: self::OUTER),
        ], [...(new BlockDependencies($blockTypes))->findIn($page)]);
    }
}
