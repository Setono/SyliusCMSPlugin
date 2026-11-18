<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Condition\Kind\DateWindowParams;
use Setono\SyliusCMSPlugin\Field\ChoicesProviderInterface;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\ParamsHydrator;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\Product\ProductAssociationsParams;
use Setono\SyliusCMSPlugin\Source\Product\ProductAssociationTypeChoicesProvider;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\LocaleIsParams;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\MismatchedParams;
use Setono\SyliusCMSPlugin\Tests\Unit\Rendering\Fixture\FixedChoiceParams;
use Sylius\Component\Core\Model\ChannelInterface;

final class ParamsHydratorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_hydrates_each_field_of_the_parameters_class_by_its_field_type(): void
    {
        $params = $this->hydrate(DateWindowParams::class, ['from' => '2026-11-27T00:00:00Z', 'until' => '2026-12-01T00:00:00Z']);

        self::assertInstanceOf(DateWindowParams::class, $params);
        self::assertEquals(new \DateTimeImmutable('2026-11-27T00:00:00Z'), $params->from);
        self::assertEquals(new \DateTimeImmutable('2026-12-01T00:00:00Z'), $params->until);
    }

    /**
     * @test
     */
    public function it_leaves_the_default_for_a_missing_value_or_one_that_does_not_fit(): void
    {
        $params = $this->hydrate(DateWindowParams::class, ['from' => '', 'until' => 'next friday']);

        self::assertInstanceOf(DateWindowParams::class, $params);
        self::assertNull($params->from);
        self::assertNull($params->until);
    }

    /**
     * @test
     */
    public function it_hydrates_a_later_field_after_one_that_is_missing_or_does_not_fit(): void
    {
        foreach ([['until' => '2026-12-01T00:00:00Z'], ['from' => 'next friday', 'until' => '2026-12-01T00:00:00Z']] as $values) {
            $params = $this->hydrate(DateWindowParams::class, $values);

            self::assertInstanceOf(DateWindowParams::class, $params);
            self::assertNull($params->from);
            self::assertEquals(new \DateTimeImmutable('2026-12-01T00:00:00Z'), $params->until);
        }
    }

    /**
     * @test
     */
    public function it_leaves_the_default_for_a_value_the_property_cannot_hold(): void
    {
        $params = $this->hydrate(MismatchedParams::class, ['count' => 'many', 'name' => 'Spring']);

        self::assertInstanceOf(MismatchedParams::class, $params);
        self::assertSame(3, $params->count);
        self::assertSame('Spring', $params->name);
    }

    /**
     * @test
     */
    public function it_ignores_values_of_fields_the_class_does_not_have(): void
    {
        $params = $this->hydrate(LocaleIsParams::class, ['locales' => ['da_DK'], 'channels' => ['WEB']]);

        self::assertInstanceOf(LocaleIsParams::class, $params);
        self::assertSame(['da_DK'], $params->locales);
    }

    /**
     * @test
     */
    public function it_gives_no_params_without_a_parameters_class(): void
    {
        self::assertInstanceOf(NoParams::class, $this->hydrate(null, ['locales' => ['da_DK']]));
    }

    /**
     * A choice from the store refers to something there, such as an association type, so a source with one set to what
     * the store no longer has yields nothing, rather than what it yields with the parameter left empty
     *
     * @test
     */
    public function it_resolves_nothing_when_a_choice_from_the_store_is_set_to_one_the_store_no_longer_has(): void
    {
        self::assertNull($this->hydrateResolved(ProductAssociationsParams::class, ['associationType' => 'GONE']));
        $hydrated = $this->hydrate(ProductAssociationsParams::class, ['associationType' => 'GONE']);
        self::assertInstanceOf(ProductAssociationsParams::class, $hydrated);
        self::assertNull($hydrated->associationType, 'Outside a source it leaves the default');
    }

    /**
     * @test
     */
    public function it_resolves_a_choice_from_the_store_that_the_store_has_or_that_was_left_empty(): void
    {
        $params = $this->hydrateResolved(ProductAssociationsParams::class, ['associationType' => 'ACCESSORIES']);
        self::assertInstanceOf(ProductAssociationsParams::class, $params);
        self::assertSame('ACCESSORIES', $params->associationType);

        $params = $this->hydrateResolved(ProductAssociationsParams::class, []);
        self::assertInstanceOf(ProductAssociationsParams::class, $params);
        self::assertNull($params->associationType);
    }

    /**
     * A fixed choice the field no longer offers, such as a sort a source dropped, leaves the default, which the source
     * reads
     *
     * @test
     */
    public function it_resolves_a_fixed_choice_the_field_no_longer_offers_as_the_default(): void
    {
        $params = $this->hydrateResolved(FixedChoiceParams::class, ['sort' => 'random']);

        self::assertInstanceOf(FixedChoiceParams::class, $params);
        self::assertSame('position', $params->sort);
    }

    /**
     * @param class-string|null $class
     * @param array<string, mixed> $values
     */
    private function hydrate(?string $class, array $values): object
    {
        return $this->hydrator()->hydrate($class, null === $class ? [] : (new FieldReader())->read($class), $values, $this->context());
    }

    /**
     * @param class-string $class
     * @param array<string, mixed> $values
     */
    private function hydrateResolved(string $class, array $values): ?object
    {
        return $this->hydrator()->hydrateResolved($class, (new FieldReader())->read($class), $values, $this->context());
    }

    private function hydrator(): ParamsHydrator
    {
        $associationTypes = $this->prophesize(ChoicesProviderInterface::class);
        $associationTypes->getChoices('en_US')->willReturn(['ACCESSORIES' => 'Accessories', 'SIMILAR' => 'Similar products']);
        $handlers = Conditions::fieldHandlers([ProductAssociationTypeChoicesProvider::class => $associationTypes->reveal()]);
        $fallbackChain = $this->prophesize(FallbackChainInterface::class);

        return new ParamsHydrator($handlers, new FallbackValues($handlers, $fallbackChain->reveal()));
    }

    private function context(): HydrationContext
    {
        return new HydrationContext(new RenderContext($this->prophesize(ChannelInterface::class)->reveal(), 'en_US', 'USD', null, '@guest', new \DateTimeImmutable()));
    }
}
