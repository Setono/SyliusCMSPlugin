<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Condition;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Condition\ConditionMatcher;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\ProductAvailable;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ProductInterface;

final class ConditionMatcherTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_shows_a_block_without_conditions(): void
    {
        self::assertTrue($this->matcher()->matchesAll([], $this->context('da_DK')));
    }

    /**
     * @test
     */
    public function it_combines_the_values_within_one_condition_with_or(): void
    {
        $condition = self::localeIs(['en_US', 'da_DK']);

        self::assertTrue($this->matcher()->matchesAll([$condition], $this->context('en_US')));
        self::assertTrue($this->matcher()->matchesAll([$condition], $this->context('da_DK')));
        self::assertFalse($this->matcher()->matchesAll([$condition], $this->context('de_DE')));
    }

    /**
     * @test
     */
    public function it_combines_a_blocks_conditions_with_and(): void
    {
        $conditions = [self::localeIs(['da_DK']), ['kind' => 'signed_in', 'negate' => false, 'params' => []]];

        self::assertTrue($this->matcher()->matchesAll($conditions, $this->context('da_DK', signedIn: true)));
        self::assertFalse($this->matcher()->matchesAll($conditions, $this->context('da_DK')), 'Only the first matches');
        self::assertFalse($this->matcher()->matchesAll($conditions, $this->context('en_US', signedIn: true)), 'Only the second matches');
    }

    /**
     * @test
     */
    public function it_reads_negate_as_is_not_one_of(): void
    {
        $condition = self::localeIs(['da_DK'], negate: true);

        self::assertFalse($this->matcher()->matches($condition, $this->context('da_DK')));
        self::assertTrue($this->matcher()->matches($condition, $this->context('en_US')));
    }

    /**
     * @test
     */
    public function it_negates_only_for_a_negate_of_true(): void
    {
        self::assertTrue($this->matcher()->matches(['kind' => 'locale_is', 'negate' => 1, 'params' => ['locales' => ['da_DK']]], $this->context('da_DK')));
        self::assertTrue($this->matcher()->matches(['kind' => 'locale_is', 'params' => ['locales' => ['da_DK']]], $this->context('da_DK')));
    }

    /**
     * @test
     */
    public function it_gives_a_kind_without_parameters_no_params(): void
    {
        self::assertTrue($this->matcher()->matches(['kind' => 'signed_in', 'negate' => false, 'params' => new \stdClass()], $this->context('en_US', signedIn: true)));
        self::assertFalse($this->matcher()->matches(['kind' => 'signed_in', 'negate' => false, 'params' => []], $this->context('en_US')));
    }

    /**
     * @test
     */
    public function it_never_matches_a_condition_of_a_kind_the_code_no_longer_has_even_negated(): void
    {
        self::assertFalse($this->matcher()->matches(['kind' => 'removed', 'negate' => false, 'params' => []], $this->context('en_US')));
        self::assertFalse($this->matcher()->matches(['kind' => 'removed', 'negate' => true, 'params' => []], $this->context('en_US')));
        self::assertFalse($this->matcher()->matches(['kind' => null, 'negate' => true, 'params' => []], $this->context('en_US')));
        self::assertFalse($this->matcher()->matchesAll([self::localeIs(['en_US']), ['kind' => 'removed', 'negate' => true, 'params' => []]], $this->context('en_US')));
    }

    /**
     * @test
     */
    public function it_leaves_the_parameters_default_for_parameter_values_that_are_missing_or_do_not_fit(): void
    {
        // A locale the choices don't have is left out, so the condition matches nobody through it
        self::assertFalse($this->matcher()->matches(['kind' => 'locale_is', 'negate' => false, 'params' => ['locales' => ['fr_FR']]], $this->context('fr_FR')));
        self::assertTrue($this->matcher()->matches(['kind' => 'locale_is', 'negate' => true, 'params' => ['locales' => ['fr_FR']]], $this->context('fr_FR')));
        self::assertFalse($this->matcher()->matches(['kind' => 'locale_is', 'negate' => false, 'params' => 'da_DK'], $this->context('da_DK')));
        self::assertFalse($this->matcher()->matches(['kind' => 'locale_is', 'negate' => false], $this->context('da_DK')));
        self::assertFalse($this->matcher()->matches(['kind' => 'locale_is', 'negate' => false, 'params' => ['locales' => 'da_DK', 'other' => ['da_DK']]], $this->context('da_DK')));
    }

    /**
     * @test
     */
    public function it_hydrates_parameters_of_a_shipped_field_type_such_as_a_date_and_time(): void
    {
        $condition = ['kind' => 'date_window', 'negate' => false, 'params' => ['from' => '2026-11-27T00:00:00Z', 'until' => '2026-12-01T00:00:00Z']];

        self::assertFalse($this->matcher()->matches($condition, $this->context('en_US', now: '2026-11-26T23:59:59Z')));
        self::assertTrue($this->matcher()->matches($condition, $this->context('en_US', now: '2026-11-27T00:00:00Z')));
        self::assertFalse($this->matcher()->matches($condition, $this->context('en_US', now: '2026-12-01T00:00:00Z')));
    }

    /**
     * A project's condition kind can take a product, a taxon or a media item, which loads with the tree's references
     *
     * @test
     */
    public function it_hydrates_a_reference_parameter_from_the_references_loaded_before_rendering(): void
    {
        $product = $this->prophesize(ProductInterface::class)->reveal();
        $condition = ['kind' => 'product_available', 'negate' => false, 'params' => ['product' => 'T_SHIRT']];
        $loaded = new ReferenceMap(['product' => ['T_SHIRT' => $product]]);
        $matcher = Conditions::matcher(['product_available' => new ProductAvailable()]);

        self::assertTrue($matcher->matches($condition, $this->context('en_US'), $loaded));
        self::assertTrue($matcher->matchesAll([$condition], $this->context('en_US'), $loaded));
        self::assertFalse($matcher->matches($condition, $this->context('en_US'), new ReferenceMap()), 'A product the shopper may not see is not loaded');
        self::assertFalse($matcher->matchesAll([$condition], $this->context('en_US')), 'Without references nothing resolves');
    }

    /**
     * @test
     */
    public function it_lists_the_references_in_the_parameters_of_the_conditions_whose_kind_it_has(): void
    {
        $matcher = Conditions::matcher(['product_available' => new ProductAvailable()]);

        $references = $matcher->references([
            ['kind' => 'product_available', 'negate' => false, 'params' => ['product' => 'T_SHIRT']],
            self::localeIs(['da_DK']),
            ['kind' => 'removed', 'negate' => false, 'params' => ['product' => 'MUG']],
            ['kind' => 'product_available', 'negate' => true, 'params' => new \stdClass()],
            ['kind' => 'product_available', 'negate' => true, 'params' => ['product' => 'CAP']],
        ]);

        self::assertEquals([new Reference('product', 'T_SHIRT', false), new Reference('product', 'CAP', false)], [...$references]);
    }

    /**
     * @param list<string> $locales
     *
     * @return array<string, mixed>
     */
    private static function localeIs(array $locales, bool $negate = false): array
    {
        return ['kind' => 'locale_is', 'negate' => $negate, 'params' => ['locales' => $locales]];
    }

    private function matcher(): ConditionMatcher
    {
        return Conditions::matcher();
    }

    private function context(string $localeCode, bool $signedIn = false, string $now = '2026-11-16T12:00:00Z'): RenderContext
    {
        return new RenderContext(
            $this->prophesize(ChannelInterface::class)->reveal(),
            $localeCode,
            'USD',
            $signedIn ? $this->prophesize(CustomerInterface::class)->reveal() : null,
            $signedIn ? '@no_group' : '@guest',
            new \DateTimeImmutable($now),
        );
    }
}
