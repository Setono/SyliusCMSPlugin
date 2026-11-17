<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Block\Column;
use Setono\SyliusCMSPlugin\Block\Columns;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Context\ConditionDependencies;
use Setono\SyliusCMSPlugin\Context\ContextDependency;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\OnProductPage;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A visibility condition whose kind needs context keys depends on the entry providing them. See "Condition kinds" in
 * docs/spec/targeting.md
 */
final class ConditionDependenciesTest extends TestCase
{
    private const HEADING = '0193a5be-0000-7000-8000-000000000001';

    private const GONE = '0193a5be-0000-7000-8000-000000000002';

    /**
     * @test
     */
    public function it_finds_every_condition_whose_kind_needs_context_keys_with_the_key_of_its_block(): void
    {
        $heading = new StoredBlock(self::HEADING, 'heading', 1, conditions: [
            ['kind' => 'signed_in', 'negate' => false, 'params' => []],
            ['kind' => 'on_product_page', 'negate' => true, 'params' => []],
            ['kind' => 'removed_kind', 'negate' => false, 'params' => []],
        ]);
        $column = new StoredBlock('0193a5be-0000-7000-8000-000000000003', 'column', 1);
        $column->children = [$heading];
        $columns = new StoredBlock('0193a5be-0000-7000-8000-000000000004', 'columns', 1);
        $columns->children = [$column];
        // A block of a type the code no longer has is named by its type
        $gone = new StoredBlock(self::GONE, 'gone', 1, conditions: [['kind' => 'on_product_page', 'negate' => false, 'params' => []]]);
        $snippet = new Snippet();
        $snippet->setBlockTree(new StoredTree(null, [$columns, $gone]));

        $condition = static fn (TranslatableMessage|string $block): TranslatableMessage => new TranslatableMessage(
            'setono_sylius_cms.context.condition',
            ['%kind%' => new TranslatableMessage('On a product page'), '%block%' => $block],
            'validators',
        );

        self::assertEquals([
            new ContextDependency($condition(new TranslatableMessage('setono_sylius_cms.block_type.heading')), ['product'], block: self::HEADING),
            new ContextDependency($condition('gone'), ['product'], block: self::GONE),
        ], [...(new ConditionDependencies(
            new BlockTypeRegistry(['columns' => Columns::class, 'column' => Column::class, 'heading' => Heading::class], new FieldReader()),
            Conditions::registry(['on_product_page' => new OnProductPage()]),
        ))->findIn($snippet)]);
    }
}
