<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Block\Column;
use Setono\SyliusCMSPlugin\Block\Columns;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Kind\UnknownConditionKind;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A condition of a kind the code no longer has never matches, negated or not, so its block stays hidden. See "Condition
 * kinds" in docs/spec/targeting.md and "Problems" in docs/spec/admin.md
 */
final class UnknownConditionKindTest extends TestCase
{
    private const A = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const B = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    private const C = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4c';

    private const D = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4d';

    /**
     * @test
     */
    public function it_finds_each_condition_of_a_kind_the_code_no_longer_has_at_any_depth_as_a_warning_fixed_in_the_builder_at_the_block(): void
    {
        $page = self::page(
            new StoredBlock(self::A, 'heading', 1, conditions: [self::condition('signed_in'), self::condition('weekday'), self::condition('brand', negate: true)]),
            new StoredBlock(self::B, 'columns', 1, children: [
                new StoredBlock(self::C, 'column', 1, conditions: [self::condition('weekday')]),
            ]),
        );

        self::assertEquals([
            self::problem($page, self::A, 'weekday', 'setono_sylius_cms.block_type.heading'),
            self::problem($page, self::A, 'brand', 'setono_sylius_cms.block_type.heading'),
            self::problem($page, self::C, 'weekday', 'setono_sylius_cms.block_type.column'),
        ], [...self::kind()->findIn($page)]);
    }

    /**
     * A block of a type the code no longer has isn't shown either, which is another kind's problem, and a condition that
     * isn't one has no kind to have lost, which publish refuses
     *
     * @test
     */
    public function it_finds_nothing_in_a_block_of_a_type_the_code_no_longer_has_or_in_a_condition_without_a_kind(): void
    {
        $page = self::page(
            new StoredBlock(self::A, 'promo', 1, conditions: [self::condition('weekday')], children: [
                new StoredBlock(self::B, 'heading', 1, conditions: [self::condition('weekday')]),
            ]),
            new StoredBlock(self::D, 'heading', 1, conditions: [['negate' => false, 'params' => []], ['kind' => 42, 'negate' => false, 'params' => []]]),
        );

        self::assertSame([], [...self::kind()->findIn($page)]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function condition(string $kind, bool $negate = false): array
    {
        return ['kind' => $kind, 'negate' => $negate, 'params' => []];
    }

    private static function problem(Page $page, string $block, string $kind, string $blockLabel): Problem
    {
        return new Problem(
            'unknown_condition_kind',
            Severity::Warning,
            $page,
            new TranslatableMessage('setono_sylius_cms.problem.unknown_condition_kind', ['%kind%' => $kind, '%block%' => new TranslatableMessage($blockLabel)]),
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

    private static function kind(): UnknownConditionKind
    {
        $blockTypes = new BlockTypeRegistry(['heading' => Heading::class, 'columns' => Columns::class, 'column' => Column::class], new FieldReader());

        return new UnknownConditionKind(new TreeValues($blockTypes, new LayoutRegistry([], new FieldReader())), $blockTypes, Conditions::registry());
    }
}
