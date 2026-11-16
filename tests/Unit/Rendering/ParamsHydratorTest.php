<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Attribute\NoParams;
use Setono\SyliusCMSPlugin\Condition\Kind\DateWindowParams;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\ParamsHydrator;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\LocaleIsParams;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\MismatchedParams;
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
     * @param class-string|null $class
     * @param array<string, mixed> $values
     */
    private function hydrate(?string $class, array $values): object
    {
        $handlers = Conditions::fieldHandlers();
        $fallbackChain = $this->prophesize(FallbackChainInterface::class);
        $hydrator = new ParamsHydrator($handlers, new FallbackValues($handlers, $fallbackChain->reveal()));

        return $hydrator->hydrate(
            $class,
            null === $class ? [] : (new FieldReader())->read($class),
            $values,
            new HydrationContext(new RenderContext($this->prophesize(ChannelInterface::class)->reveal(), 'en_US', 'USD', null, '@guest', new \DateTimeImmutable())),
        );
    }
}
