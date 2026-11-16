<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Block\Column;
use Setono\SyliusCMSPlugin\Block\Columns;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Kind\UnknownBlockType;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Component\Translation\TranslatableMessage;

final class UnknownBlockTypeTest extends TestCase
{
    private const A = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const B = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    private const C = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4c';

    private const D = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4d';

    private const E = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4e';

    /**
     * @test
     */
    public function it_finds_a_block_of_a_type_the_code_no_longer_has_at_any_depth_as_a_warning_fixed_in_the_builder_at_the_block(): void
    {
        $page = self::page(
            new StoredBlock(self::A, 'heading', 1),
            new StoredBlock(self::B, 'promo', 1),
            new StoredBlock(self::C, 'columns', 1, children: [
                new StoredBlock(self::D, 'column', 1, children: [new StoredBlock(self::E, 'carousel', 2)]),
            ]),
        );

        self::assertEquals([
            self::problem($page, self::B, 'promo'),
            self::problem($page, self::E, 'carousel'),
        ], [...self::kind()->findIn($page)]);
    }

    /**
     * @test
     */
    public function it_finds_nothing_more_inside_a_block_of_an_unknown_type_which_is_kept_with_what_is_inside_it(): void
    {
        $page = self::page(new StoredBlock(self::A, 'promo', 1, children: [new StoredBlock(self::B, 'banner', 1)]));

        self::assertEquals([self::problem($page, self::A, 'promo')], [...self::kind()->findIn($page)]);
    }

    /**
     * @test
     */
    public function it_finds_nothing_in_a_tree_of_known_block_types(): void
    {
        $page = self::page(new StoredBlock(self::A, 'columns', 1, children: [new StoredBlock(self::B, 'column', 1)]));

        self::assertSame([], [...self::kind()->findIn($page)]);
    }

    private static function problem(Page $page, string $block, string $type): Problem
    {
        return new Problem(
            'unknown_block_type',
            Severity::Warning,
            $page,
            new TranslatableMessage('setono_sylius_cms.problem.unknown_block_type', ['%type%' => $type]),
            Fix::inBuilder($page, $block),
            $block,
        );
    }

    private static function page(StoredBlock ...$blocks): Page
    {
        $page = new Page();
        $page->setBlockTree(new StoredTree(null, array_values($blocks)));

        return $page;
    }

    private static function kind(): UnknownBlockType
    {
        return new UnknownBlockType(new BlockTypeRegistry(
            ['heading' => Heading::class, 'columns' => Columns::class, 'column' => Column::class],
            new FieldReader(),
        ));
    }
}
