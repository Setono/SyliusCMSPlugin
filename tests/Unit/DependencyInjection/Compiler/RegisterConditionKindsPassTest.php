<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\DependencyInjection\Compiler;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Kind\DateWindowKind;
use Setono\SyliusCMSPlugin\Condition\TimeDependentConditionKindInterface;
use Setono\SyliusCMSPlugin\DependencyInjection\Compiler\RegisterConditionKindsPass;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistry;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\InvalidCode;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\LocaleIs;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\LocaleIsParams;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\MissingParams;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\NotAKind;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\ReadsTimeWithoutNextChange;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\SameCodeAsLocaleIs;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\SignedIn;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class RegisterConditionKindsPassTest extends AbstractCompilerPassTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setDefinition(ConditionKindRegistry::class, new Definition(ConditionKindRegistry::class, [[], null, []]));
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RegisterConditionKindsPass());
    }

    /**
     * @test
     */
    public function it_hands_the_condition_kinds_to_the_registry_by_code(): void
    {
        $this->setKind('app.locale_is', LocaleIs::class);
        $this->setKind(SignedIn::class, SignedIn::class);
        $this->setParameter('app.date_window.class', DateWindowKind::class);
        $this->setKind('app.date_window', '%app.date_window.class%');

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ConditionKindRegistry::class, 0, [
            'locale_is' => LocaleIs::class,
            'signed_in' => SignedIn::class,
            'date_window' => DateWindowKind::class,
        ]);
        self::assertEquals([
            'locale_is' => new ServiceClosureArgument(new Reference('app.locale_is')),
            'signed_in' => new ServiceClosureArgument(new Reference(SignedIn::class)),
            'date_window' => new ServiceClosureArgument(new Reference('app.date_window')),
        ], $this->locatorArgument());
    }

    /**
     * @test
     */
    public function it_accepts_one_kind_registered_twice(): void
    {
        $this->setKind('app.locale_is', LocaleIs::class);
        $this->setKind(LocaleIs::class, LocaleIs::class);

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ConditionKindRegistry::class, 0, ['locale_is' => LocaleIs::class]);
        self::assertEquals(['locale_is' => new ServiceClosureArgument(new Reference(LocaleIs::class))], $this->locatorArgument());
    }

    /**
     * @test
     */
    public function it_fails_the_build_for_two_kinds_with_the_same_code(): void
    {
        $this->setKind('app.locale_is', LocaleIs::class);
        $this->setKind('app.other', SameCodeAsLocaleIs::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The condition kind code "locale_is" is used by both %s and %s. Codes are unique per kind, so give one of them another code', LocaleIs::class, SameCodeAsLocaleIs::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_tagged_class_without_the_attribute(): void
    {
        $this->setKind('app.params', LocaleIsParams::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The service "app.params" is tagged "setono_sylius_cms.condition_kind", but its class %s has no #[AsConditionKind]', LocaleIsParams::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_an_invalid_code(): void
    {
        $this->setKind('app.invalid', InvalidCode::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The condition kind code "Invalid-Code" of %s must match /^[a-z][a-z0-9_]*$/', InvalidCode::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_kind_that_does_not_implement_the_interface(): void
    {
        $this->setKind('app.not_a_kind', NotAKind::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The condition kind %s must implement %s', NotAKind::class, ConditionKindInterface::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_kind_that_reads_the_time_without_reporting_its_next_change(): void
    {
        $this->setKind('app.clock', ReadsTimeWithoutNextChange::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The condition kind %s reads the time, so it must implement %s', ReadsTimeWithoutNextChange::class, TimeDependentConditionKindInterface::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_refuses_a_parameters_class_that_does_not_exist(): void
    {
        $this->setKind('app.missing_params', MissingParams::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The parameters class App\Condition\MissingParams of the condition kind %s doesn\'t exist', MissingParams::class));

        $this->compile();
    }

    /**
     * @test
     */
    public function it_does_nothing_without_the_registry(): void
    {
        $this->container->removeDefinition(ConditionKindRegistry::class);
        $this->setKind('app.not_a_kind', NotAKind::class);

        $this->compile();

        $this->assertContainerBuilderNotHasService(ConditionKindRegistry::class);
    }

    private function setKind(string $id, string $class): void
    {
        $this->setDefinition($id, (new Definition($class))->addTag('setono_sylius_cms.condition_kind'));
    }

    /**
     * @return array<string, mixed>
     */
    private function locatorArgument(): array
    {
        $argument = $this->container->getDefinition(ConditionKindRegistry::class)->getArgument(2);
        self::assertInstanceOf(Reference::class, $argument);

        $locator = $this->container->getDefinition((string) $argument);
        self::assertSame(ServiceLocator::class, $locator->getClass());

        $kinds = $locator->getArgument(0);
        self::assertIsArray($kinds);

        return $kinds;
    }
}
