<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\ChoicesProviderInterface;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Sylius\Component\Core\Model\ChannelInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture\ChannelsByLocale;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ChoiceHandlerTest extends TestCase
{
    use ProphecyTrait;

    private const CHOICES = ['h1' => 'setono_sylius_cms.choice.heading_level.h1', 'h2' => 'setono_sylius_cms.choice.heading_level.h2'];

    /** @var ObjectProphecy<TranslatorInterface> */
    private ObjectProphecy $translator;

    protected function setUp(): void
    {
        $this->translator = $this->prophesize(TranslatorInterface::class);
        $this->translator->trans('setono_sylius_cms.choice.heading_level.h1')->willReturn('Heading 1');
        $this->translator->trans('setono_sylius_cms.choice.heading_level.h2')->willReturn('Heading 2');
    }

    /**
     * @test
     */
    public function its_schema_is_one_of_the_choices_with_translated_labels_in_its_options(): void
    {
        self::assertSame(
            [
                'type' => 'string',
                'enum' => ['h1', 'h2'],
                'x-cms-options' => ['choices' => ['h1' => 'Heading 1', 'h2' => 'Heading 2'], 'multiple' => false],
            ],
            $this->handler()->schema(new Choice(label: 'Level', choices: self::CHOICES)),
        );
    }

    /**
     * @test
     */
    public function its_schema_is_a_list_of_unique_choices_when_multiple(): void
    {
        self::assertSame(
            [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => ['h1', 'h2']],
                'uniqueItems' => true,
                'x-cms-options' => ['choices' => ['h1' => 'Heading 1', 'h2' => 'Heading 2'], 'multiple' => true],
            ],
            $this->handler()->schema(new Choice(label: 'Levels', choices: self::CHOICES, multiple: true)),
        );
    }

    /**
     * @test
     */
    public function its_schema_writes_numeric_choice_values_as_strings(): void
    {
        $this->translator->trans('setono_sylius_cms.choice.spacer_size.small')->willReturn('Small');

        $schema = $this->handler()->schema(new Choice(label: 'Size', choices: ['1' => 'setono_sylius_cms.choice.spacer_size.small']));

        self::assertSame(['1'], $schema['enum']);
    }

    /**
     * @test
     */
    public function it_hydrates_a_chosen_value(): void
    {
        self::assertSame('h2', $this->handler()->hydrate(new Choice(label: 'Level', choices: self::CHOICES), 'h2', $this->hydrationContext()));
    }

    /**
     * @test
     */
    public function it_hydrates_a_value_that_is_not_a_choice_as_missing(): void
    {
        $field = new Choice(label: 'Level', choices: self::CHOICES);

        self::assertNull($this->handler()->hydrate($field, 'h6', $this->hydrationContext()));
        self::assertNull($this->handler()->hydrate($field, ['h1'], $this->hydrationContext()));
        self::assertNull($this->handler()->hydrate($field, null, $this->hydrationContext()));
    }

    /**
     * @test
     */
    public function it_hydrates_the_valid_values_of_a_multiple_choice_as_a_list(): void
    {
        $field = new Choice(label: 'Levels', choices: self::CHOICES, multiple: true);

        self::assertSame(['h2', 'h1'], $this->handler()->hydrate($field, ['h2', 'h6', 'h1', 3], $this->hydrationContext()));
        self::assertSame([], $this->handler()->hydrate($field, 'h1', $this->hydrationContext()));
    }

    /**
     * @test
     */
    public function it_fits_only_a_choice_it_still_offers(): void
    {
        self::assertTrue($this->handler()->fits(new Choice(label: 'Level', choices: self::CHOICES), 'h1'));
        self::assertTrue($this->handler()->fits(new Choice(label: 'Levels', choices: self::CHOICES, multiple: true), ['h1', 'h2']));
        self::assertFalse($this->handler()->fits(new Choice(label: 'Level', choices: self::CHOICES), 'h6'));
        self::assertFalse($this->handler()->fits(new Choice(label: 'Levels', choices: self::CHOICES, multiple: true), ['h1', 'h6']));
    }

    /**
     * @test
     */
    public function it_holds_no_references(): void
    {
        self::assertSame([], [...$this->handler()->references(new Choice(label: 'Level', choices: self::CHOICES), 'h1')]);
    }

    /**
     * @test
     */
    public function it_accepts_a_choice_and_a_missing_value(): void
    {
        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation(Argument::cetera())->shouldNotBeCalled();

        $this->handler()->validate(new Choice(label: 'Level', choices: self::CHOICES), 'h1', $context->reveal());
        $this->handler()->validate(new Choice(label: 'Level', choices: self::CHOICES), null, $context->reveal());
        $this->handler()->validate(new Choice(label: 'Levels', choices: self::CHOICES, multiple: true), ['h1', 'h2'], $context->reveal());
        $this->handler()->validate(new Choice(label: 'Levels', choices: self::CHOICES, multiple: true), [], $context->reveal());
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidValues
     */
    public function it_rejects_a_value_that_is_not_a_choice(bool $multiple, mixed $value): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('setono_sylius_cms.field.invalid_choice')->willReturn($builder->reveal())->shouldBeCalledOnce();

        $this->handler()->validate(new Choice(label: 'Level', choices: self::CHOICES, multiple: $multiple), $value, $context->reveal());
    }

    /**
     * @return iterable<string, array{bool, mixed}>
     */
    public function provideInvalidValues(): iterable
    {
        yield 'an unknown value' => [false, 'h6'];
        yield 'a list for a single choice' => [false, ['h1']];
        yield 'a string for a multiple choice' => [true, 'h1'];
        yield 'an unknown value in a multiple choice' => [true, ['h1', 'h6']];
        yield 'a non-string in a multiple choice' => [true, ['h1', 1]];
        yield 'a map for a multiple choice' => [true, ['a' => 'h1']];
    }

    /**
     * @test
     */
    public function it_refuses_a_field_of_another_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->handler()->schema(new Text(label: 'Heading'));
    }

    /**
     * @test
     */
    public function its_schema_has_the_choices_a_provider_gives_in_the_translators_locale(): void
    {
        $translator = new IdentityTranslator();
        $translator->setLocale('da_DK');

        self::assertSame(
            [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => ['WEB', '42']],
                'uniqueItems' => true,
                'x-cms-options' => ['choices' => ['WEB' => 'Webbutik', 42 => 'Butik 42'], 'multiple' => true],
            ],
            $this->providing($translator)->schema(self::fromProvider()),
        );
    }

    /**
     * @test
     */
    public function it_asks_a_provider_in_english_when_the_translator_has_no_locale(): void
    {
        $schema = $this->providing($this->translator->reveal())->schema(self::fromProvider());

        self::assertSame(['choices' => ['WEB' => 'Web store', 42 => 'Store 42', 'B2B' => 'Wholesale'], 'multiple' => true], $schema['x-cms-options']);
    }

    /**
     * @test
     */
    public function it_hydrates_only_the_values_a_provider_gives_in_the_shoppers_locale(): void
    {
        $translator = new IdentityTranslator();
        $translator->setLocale('da_DK');
        $handler = $this->providing($translator);

        self::assertSame(['42', 'WEB'], $handler->hydrate(self::fromProvider(), ['42', 'B2B', 'WEB'], $this->hydrationContext('da_DK')));
        self::assertSame(['42', 'B2B', 'WEB'], $handler->hydrate(self::fromProvider(), ['42', 'B2B', 'WEB'], $this->hydrationContext('en_US')));
        self::assertSame('WEB', $handler->hydrate(self::fromProvider(multiple: false), 'WEB', $this->hydrationContext('da_DK')));
        self::assertNull($handler->hydrate(self::fromProvider(multiple: false), 'B2B', $this->hydrationContext('da_DK')));
    }

    /**
     * @test
     */
    public function it_fits_only_the_values_a_provider_gives_in_the_translators_locale(): void
    {
        $translator = new IdentityTranslator();
        $translator->setLocale('da_DK');

        self::assertTrue($this->providing($translator)->fits(self::fromProvider(), ['WEB', '42']));
        self::assertFalse($this->providing($translator)->fits(self::fromProvider(), ['WEB', 'B2B']));
        self::assertTrue($this->providing(new IdentityTranslator())->fits(self::fromProvider(), ['WEB', 'B2B']));
    }

    /**
     * @test
     */
    public function it_refuses_a_provider_that_is_not_registered(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The choices provider %s isn\'t registered', ChannelsByLocale::class));

        $this->handler()->schema(self::fromProvider());
    }

    /**
     * @test
     */
    public function it_refuses_a_provider_that_does_not_implement_the_interface(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('The choices provider %s must implement %s', ChannelsByLocale::class, ChoicesProviderInterface::class));

        (new ChoiceHandler(new IdentityTranslator(), new ServiceLocator([ChannelsByLocale::class => static fn (): \stdClass => new \stdClass()])))->schema(self::fromProvider());
    }

    private static function fromProvider(bool $multiple = true): Choice
    {
        return new Choice(label: 'Channels', multiple: $multiple, choicesProvider: ChannelsByLocale::class);
    }

    private function providing(TranslatorInterface $translator): ChoiceHandler
    {
        return new ChoiceHandler($translator, new ServiceLocator([ChannelsByLocale::class => static fn (): ChannelsByLocale => new ChannelsByLocale()]));
    }

    private function handler(): ChoiceHandler
    {
        return new ChoiceHandler($this->translator->reveal());
    }

    private function hydrationContext(string $localeCode = 'en_US'): HydrationContext
    {
        return new HydrationContext(new RenderContext(
            $this->prophesize(ChannelInterface::class)->reveal(),
            $localeCode,
            'USD',
            null,
            '@guest',
            new \DateTimeImmutable(),
        ));
    }
}
