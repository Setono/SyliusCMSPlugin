<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Slot;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Condition\Kind\ProductInTaxonKind;
use Setono\SyliusCMSPlugin\Condition\Kind\TaxonKind;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\ListFieldHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Localization\FallbackChain;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Registry\ContextKeyRegistry;
use Setono\SyliusCMSPlugin\Registry\SlotRegistry;
use Setono\SyliusCMSPlugin\Slot\PageScopeValidator;
use Setono\SyliusCMSPlugin\Tests\Support\Bindings;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Support\Sources;
use Setono\SyliusCMSPlugin\Validation\FieldConstraints;
use Setono\SyliusCMSPlugin\Validation\FieldValidator;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Resource\Translation\Provider\ImmutableTranslationLocaleProvider;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Validator\Validation;

/**
 * What a placement's page scope may hold: conditions in the stored condition format, of kinds that read the host page
 * and whose needs the slot provides, with parameter values their fields accept. See "Page scopes" in
 * docs/spec/slots.md
 */
final class PageScopeValidatorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_accepts_an_empty_scope_and_conditions_on_the_context_the_slot_provides(): void
    {
        self::assertSame([], $this->validator()->validate('product.after_price', []));
        self::assertSame([], $this->validator()->validate('shop.footer', []));
        self::assertSame([], $this->validator()->validate('product.after_price', [
            self::inTaxons(['SHOES']),
            ['kind' => 'product_in_taxon', 'negate' => true, 'params' => ['taxons' => ['SALE'], 'includeChildren' => true]],
        ]));
    }

    /**
     * @test
     */
    public function it_refuses_a_condition_that_is_not_in_the_stored_condition_format(): void
    {
        $malformed = static fn (int $number): TranslatableMessage => new TranslatableMessage('setono_sylius_cms.page_scope.malformed', ['%number%' => $number], 'validators');

        self::assertEquals([$malformed(1), $malformed(2), $malformed(3), $malformed(4), $malformed(5)], $this->validator()->validate('product.after_price', [
            'product_in_taxon',
            ['kind' => 'Product in taxon', 'negate' => false, 'params' => ['taxons' => ['SHOES']]],
            ['kind' => 'product_in_taxon', 'negate' => 'no', 'params' => ['taxons' => ['SHOES']]],
            ['kind' => 'product_in_taxon', 'negate' => false, 'params' => [['SHOES']]],
            ['kind' => 'product_in_taxon', 'negate' => false],
        ]));
    }

    /**
     * @test
     */
    public function it_refuses_a_condition_of_a_kind_the_code_does_not_have(): void
    {
        self::assertEquals(
            [new TranslatableMessage('setono_sylius_cms.page_scope.unknown_kind', ['%number%' => 2, '%kind%' => 'brand'], 'validators')],
            $this->validator()->validate('product.after_price', [self::inTaxons(['SHOES']), ['kind' => 'brand', 'negate' => false, 'params' => []]]),
        );
    }

    /**
     * @test
     */
    public function it_refuses_a_condition_of_a_kind_that_does_not_read_the_host_page(): void
    {
        self::assertEquals(
            [new TranslatableMessage('setono_sylius_cms.page_scope.not_host_page', ['%number%' => 1, '%kind%' => new TranslatableMessage(Conditions::registry()->get('locale_is')->label)], 'validators')],
            $this->validator()->validate('product.after_price', [['kind' => 'locale_is', 'negate' => false, 'params' => ['locales' => ['da_DK']]]]),
        );
    }

    /**
     * @test
     */
    public function it_refuses_a_condition_whose_kind_needs_context_the_slot_does_not_provide(): void
    {
        self::assertEquals(
            [
                new TranslatableMessage('setono_sylius_cms.page_scope.needs', ['%number%' => 1, '%kind%' => new TranslatableMessage('setono_sylius_cms.condition_kind.product_in_taxon'), '%context%' => new TranslatableMessage('setono_sylius_cms.context_key.product')], 'validators'),
            ],
            $this->validator()->validate('shop.footer', [self::inTaxons(['SHOES'])]),
        );
        self::assertEquals(
            [
                new TranslatableMessage('setono_sylius_cms.page_scope.needs', ['%number%' => 1, '%kind%' => new TranslatableMessage('setono_sylius_cms.condition_kind.taxon'), '%context%' => new TranslatableMessage('setono_sylius_cms.context_key.taxon')], 'validators'),
            ],
            $this->validator()->validate('product.after_price', [['kind' => 'taxon', 'negate' => false, 'params' => ['taxons' => ['SHOES']]]]),
        );
    }

    /**
     * @test
     */
    public function it_refuses_parameter_values_the_kind_s_fields_refuse(): void
    {
        $field = static fn (string $message): TranslatableMessage => new TranslatableMessage('setono_sylius_cms.page_scope.field', [
            '%number%' => 1,
            '%kind%' => new TranslatableMessage('setono_sylius_cms.condition_kind.product_in_taxon'),
            '%field%' => new TranslatableMessage('setono_sylius_cms.field.product_in_taxon_condition.taxons'),
            '%message%' => $message,
        ], 'validators');

        self::assertEquals([$field('setono_sylius_cms.field.required')], $this->validator()->validate('product.after_price', [self::inTaxons([])]));
        self::assertEquals([$field('setono_sylius_cms.field.not_list')], $this->validator()->validate('product.after_price', [['kind' => 'product_in_taxon', 'negate' => false, 'params' => ['taxons' => 'SHOES']]]));
    }

    /**
     * A placement in a slot a deploy removed shows nothing, and can only lose its scope
     *
     * @test
     */
    public function it_refuses_every_condition_in_a_slot_the_code_does_not_declare(): void
    {
        self::assertSame([], $this->validator()->validate('shop.gone', []));
        self::assertEquals(
            [new TranslatableMessage('setono_sylius_cms.page_scope.removed_slot', ['%slot%' => 'shop.gone'], 'validators')],
            $this->validator()->validate('shop.gone', [self::inTaxons(['SHOES'])]),
        );
    }

    /**
     * @param list<string> $taxons
     *
     * @return array<string, mixed>
     */
    private static function inTaxons(array $taxons): array
    {
        return ['kind' => 'product_in_taxon', 'negate' => false, 'params' => ['taxons' => $taxons]];
    }

    private function validator(): PageScopeValidator
    {
        $translator = new IdentityTranslator();
        $handlers = new FieldHandlerLocator(new ServiceLocator([
            ListFieldHandler::class => static fn (): ListFieldHandler => Sources::listFieldHandler(),
            ToggleHandler::class => static fn (): ToggleHandler => new ToggleHandler(),
        ]));

        return new PageScopeValidator(
            Conditions::registry(['product_in_taxon' => new ProductInTaxonKind(), 'taxon' => new TaxonKind()]),
            new SlotRegistry([
                'shop.footer' => ['label' => 'setono_sylius_cms.slot.shop.footer', 'provides' => [], 'cap' => null],
                'product.after_price' => ['label' => 'setono_sylius_cms.slot.product.after_price', 'provides' => ['product'], 'cap' => null],
            ]),
            new ContextKeyRegistry([
                'product' => ['class' => ProductInterface::class, 'label' => 'setono_sylius_cms.context_key.product'],
                'taxon' => ['class' => TaxonInterface::class, 'label' => 'setono_sylius_cms.context_key.taxon'],
            ]),
            new FieldValidator(
                new FieldConstraints($handlers, $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->setTranslator($translator)->setTranslationDomain('validators')->getValidator()),
                new FallbackValues($handlers, new FallbackChain(new ImmutableTranslationLocaleProvider(['en_US'], 'en_US'))),
                $validator,
                $translator,
                Bindings::validator(),
            ),
        );
    }
}
