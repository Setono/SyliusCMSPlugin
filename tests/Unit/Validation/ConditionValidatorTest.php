<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Condition\Kind\ChannelKind;
use Setono\SyliusCMSPlugin\Condition\Kind\CustomerGroupKind;
use Setono\SyliusCMSPlugin\Condition\Kind\LocaleKind;
use Setono\SyliusCMSPlugin\Condition\Kind\ProductInTaxonKind;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\ChoicesProviderInterface;
use Setono\SyliusCMSPlugin\Field\DateTimeHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\ListFieldHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Localization\FallbackChain;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Targeting\ChannelChoicesProvider;
use Setono\SyliusCMSPlugin\Targeting\CustomerGroupChoicesProvider;
use Setono\SyliusCMSPlugin\Targeting\LocaleChoicesProvider;
use Setono\SyliusCMSPlugin\Tests\Support\Bindings;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Support\Sources;
use Setono\SyliusCMSPlugin\Validation\ConditionValidator;
use Setono\SyliusCMSPlugin\Validation\FieldConstraints;
use Setono\SyliusCMSPlugin\Validation\FieldValidator;
use Setono\SyliusCMSPlugin\Validation\ValidationError;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Translation\Provider\ImmutableTranslationLocaleProvider;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Validator\Validation;

/**
 * A visibility condition is refused on publish when it isn't one, its kind is one the code no longer has, or its
 * parameter values don't fit its kind, such as a date window that ends before it starts. See "Validation" in
 * docs/spec/publishing.md
 */
final class ConditionValidatorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_accepts_a_condition_whose_parameters_fit_its_kind(): void
    {
        self::assertSame([], $this->validator()->validate(['kind' => 'locale_is', 'negate' => true, 'params' => ['locales' => ['da_DK']]]));
        self::assertSame([], $this->validator()->validate(['kind' => 'date_window', 'negate' => false, 'params' => ['from' => '2026-12-01T00:00:00Z', 'until' => '2026-12-24T00:00:00Z']]));
        self::assertSame([], $this->validator()->validate(['kind' => 'date_window', 'negate' => false, 'params' => ['until' => '2026-12-24T00:00:00Z']]));
        self::assertSame([], $this->validator()->validate(['kind' => 'signed_in', 'negate' => false, 'params' => []]));
    }

    /**
     * @test
     */
    public function it_refuses_a_parameter_value_its_field_refuses_at_that_parameter(): void
    {
        self::assertEquals(
            [new ValidationError(null, 'locales', null, 'The value you selected is not a valid choice.')],
            $this->validator()->validate(['kind' => 'locale_is', 'negate' => false, 'params' => ['locales' => ['sv_SE']]]),
        );
        self::assertEquals(
            [new ValidationError(null, 'from', null, 'This value should be a date and time.')],
            $this->validator()->validate(['kind' => 'date_window', 'negate' => false, 'params' => ['from' => 'next Monday']]),
        );
        self::assertEquals(
            [new ValidationError(null, 'taxons', null, 'This field is required.')],
            $this->validator()->validate(['kind' => 'product_in_taxon', 'negate' => false, 'params' => ['taxons' => [], 'includeChildren' => true]]),
        );
    }

    /**
     * A code the store no longer has is a broken reference, which the admin flags, not a value that doesn't fit, so a
     * deleted channel, locale or customer group doesn't block publish (ADR 0002). See "Validation" in
     * docs/spec/publishing.md
     *
     * @test
     */
    public function it_accepts_a_channel_locale_or_customer_group_the_store_no_longer_has(): void
    {
        self::assertSame([], $this->validator()->validate(['kind' => 'channel', 'negate' => false, 'params' => ['channels' => ['WEB', 'OUTLET']]]));
        self::assertSame([], $this->validator()->validate(['kind' => 'locale', 'negate' => true, 'params' => ['locales' => ['sv_SE']]]));
        self::assertSame([], $this->validator()->validate(['kind' => 'customer_group', 'negate' => false, 'params' => ['groups' => ['@guest', 'wholesale']]]));
    }

    /**
     * @test
     */
    public function it_refuses_a_channel_locale_or_customer_group_parameter_of_the_wrong_shape(): void
    {
        foreach ([['channel', 'channels', 'WEB'], ['locale', 'locales', ['en_US', 7]], ['customer_group', 'groups', ['a' => 'retail']]] as [$kind, $param, $value]) {
            self::assertEquals(
                [new ValidationError(null, $param, null, 'The value you selected is not a valid choice.')],
                $this->validator()->validate(['kind' => $kind, 'negate' => false, 'params' => [$param => $value]]),
            );
        }
    }

    /**
     * "Until" is exclusive, so a window that ends where it starts never shows either
     *
     * @test
     */
    public function it_refuses_a_date_window_that_ends_before_it_starts_or_where_it_starts_at_its_end(): void
    {
        foreach (['2026-11-30T00:00:00Z', '2026-12-01T00:00:00Z'] as $until) {
            self::assertEquals(
                [new ValidationError(null, 'until', null, 'Until must come after From.')],
                $this->validator()->validate(['kind' => 'date_window', 'negate' => true, 'params' => ['from' => '2026-12-01T00:00:00Z', 'until' => $until]]),
            );
        }
    }

    /**
     * @test
     */
    public function it_refuses_a_condition_of_a_kind_the_code_no_longer_has_or_that_is_not_a_condition_as_a_whole(): void
    {
        self::assertEquals(
            [new ValidationError(null, null, null, 'The condition kind "brand" no longer exists, so the block isn\'t shown. Remove the condition.')],
            $this->validator()->validate(['kind' => 'brand', 'negate' => false, 'params' => ['brands' => ['ACME']]]),
        );
        self::assertEquals(
            [new ValidationError(null, null, null, 'This isn\'t a condition. Remove it.')],
            $this->validator()->validate(['kind' => 'locale_is', 'negate' => 'no', 'params' => []]),
        );
    }

    private function validator(): ConditionValidator
    {
        $translator = BlockTreeValidatorTest::translator();
        $providers = $this->storeChoices();
        $handlers = new FieldHandlerLocator(new ServiceLocator([
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler($translator, $providers),
            DateTimeHandler::class => static fn (): DateTimeHandler => new DateTimeHandler(),
            ListFieldHandler::class => static fn (): ListFieldHandler => Sources::listFieldHandler(),
            ToggleHandler::class => static fn (): ToggleHandler => new ToggleHandler(),
        ]));
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->setTranslator($translator)->setTranslationDomain('validators')->getValidator();
        $registry = Conditions::registry([
            'product_in_taxon' => new ProductInTaxonKind(),
            'channel' => new ChannelKind(),
            'locale' => new LocaleKind(),
            'customer_group' => new CustomerGroupKind(),
        ]);

        return new ConditionValidator(
            $registry,
            new FieldValidator(
                new FieldConstraints($handlers, $validator),
                new FallbackValues($handlers, new FallbackChain(new ImmutableTranslationLocaleProvider(['en_US'], 'en_US'))),
                $validator,
                $translator,
                Bindings::validator(),
            ),
            $translator,
        );
    }

    /**
     * The store's channel WEB, locale en_US and customer group retail
     *
     * @return ServiceLocator<ChoicesProviderInterface>
     */
    private function storeChoices(): ServiceLocator
    {
        $web = $this->prophesize(ChannelInterface::class);
        $web->getCode()->willReturn('WEB');
        $web->getName()->willReturn('Web store');
        /** @var ObjectProphecy<ChannelRepositoryInterface<ChannelInterface>> $channels */
        $channels = $this->prophesize(ChannelRepositoryInterface::class);
        $channels->findBy([], ['name' => 'ASC'])->willReturn([$web->reveal()]);

        $english = $this->prophesize(LocaleInterface::class);
        $english->getCode()->willReturn('en_US');
        $english->getName('en')->willReturn('English (United States)');
        /** @var ObjectProphecy<RepositoryInterface<LocaleInterface>> $locales */
        $locales = $this->prophesize(RepositoryInterface::class);
        $locales->findBy([], ['code' => 'ASC'])->willReturn([$english->reveal()]);

        $retail = $this->prophesize(CustomerGroupInterface::class);
        $retail->getCode()->willReturn('retail');
        $retail->getName()->willReturn('Retail');
        /** @var ObjectProphecy<RepositoryInterface<CustomerGroupInterface>> $groups */
        $groups = $this->prophesize(RepositoryInterface::class);
        $groups->findBy([], ['name' => 'ASC'])->willReturn([$retail->reveal()]);

        return new ServiceLocator([
            ChannelChoicesProvider::class => static fn (): ChannelChoicesProvider => new ChannelChoicesProvider($channels->reveal()),
            LocaleChoicesProvider::class => static fn (): LocaleChoicesProvider => new LocaleChoicesProvider($locales->reveal()),
            CustomerGroupChoicesProvider::class => static fn (): CustomerGroupChoicesProvider => new CustomerGroupChoicesProvider($groups->reveal(), new IdentityTranslator()),
        ]);
    }
}
