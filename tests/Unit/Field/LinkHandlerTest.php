<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Field;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\Link;
use Setono\SyliusCMSPlugin\Field\LinkHandler;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Link\ResolvedLink;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Links;
use Setono\SyliusCMSPlugin\Tests\Unit\Field\Fixture\WithLinks;
use Setono\SyliusCMSPlugin\Tests\Unit\Link\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Value\Link as LinkValue;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class LinkHandlerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_is_the_shipped_link_field_type_whose_value_is_shared_unless_it_is_a_whole_link_per_locale(): void
    {
        $fields = (new FieldReader())->read(WithLinks::class);

        self::assertSame('link', $fields['link']->type->code);
        self::assertSame(LinkHandler::class, $fields['link']->handler());
        self::assertFalse($fields['link']->translatable);
        self::assertFalse($fields['oneForEveryLocale']->translatable);
        self::assertTrue($fields['perLocale']->translatable);
    }

    /**
     * @test
     */
    public function it_links_to_at_least_one_target(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Link(label: 'Link', targets: []);
    }

    /**
     * @test
     */
    public function its_schema_keeps_the_label_and_a_translatable_ref_per_locale_by_default(): void
    {
        $schema = Links::handler()->schema(new Link(label: 'Link'));

        self::assertSame('object', $schema['type']);
        self::assertSame(['target', 'ref'], $schema['required']);
        self::assertSame(['type' => 'string', 'enum' => ['url', 'app_store', 'recipe']], $schema['properties']['target']);
        self::assertSame(['oneOf' => [['type' => 'string'], ['type' => 'object', 'additionalProperties' => ['type' => 'string']]]], $schema['properties']['ref']);
        self::assertSame(['type' => ['string', 'null']], $schema['properties']['anchor']);
        self::assertSame(['type' => 'object', 'additionalProperties' => ['type' => 'string']], $schema['properties']['label']);
        self::assertSame(['targets' => ['url', 'app_store', 'recipe'], 'translatable' => null], $schema['x-cms-options']);
    }

    /**
     * @test
     */
    public function its_schema_keeps_one_label_and_one_ref_when_it_is_one_link_for_every_locale_or_a_link_per_locale(): void
    {
        foreach ([false, true] as $translatable) {
            $schema = Links::handler()->schema(new Link(label: 'Link', targets: ['app_store', 'url'], translatable: $translatable));

            self::assertSame(['type' => 'string', 'enum' => ['app_store', 'url']], $schema['properties']['target']);
            self::assertSame(['type' => 'string'], $schema['properties']['ref']);
            self::assertSame(['type' => ['string', 'null']], $schema['properties']['label']);
            self::assertSame(['targets' => ['app_store', 'url'], 'translatable' => $translatable], $schema['x-cms-options']);
        }
    }

    /**
     * @test
     */
    public function its_schema_offers_only_the_targets_that_exist(): void
    {
        $schema = Links::handler()->schema(new Link(label: 'Link', targets: ['media', 'url']));

        self::assertSame(['url'], $schema['x-cms-options']['targets']);
    }

    /**
     * @test
     */
    public function it_hydrates_the_label_of_the_current_locale(): void
    {
        $link = Links::handler()->hydrate(new Link(label: 'Link'), self::storeLink(['en_US' => 'Visit us', 'de_DE' => 'Besuchen Sie uns']), $this->context('de_DE'));

        self::assertInstanceOf(LinkValue::class, $link);
        self::assertSame('app_store', $link->target);
        self::assertSame('copenhagen', $link->ref);
        self::assertSame('Besuchen Sie uns', $link->label);
        self::assertEquals(new ResolvedLink('app_store', 'copenhagen', '/de_DE/stores/copenhagen', 'Copenhagen'), $link->resolved);
    }

    /**
     * @test
     */
    public function it_picks_the_label_along_the_fallback_chain_past_an_empty_one(): void
    {
        $handler = Links::handler(['da_DK' => ['de_DE']]);
        $value = self::storeLink(['en_US' => 'Visit us', 'de_DE' => 'Besuchen Sie uns', 'da_DK' => '']);

        self::assertSame('Besuchen Sie uns', $handler->hydrate(new Link(label: 'Link'), $value, $this->context('da_DK'))?->label);
    }

    /**
     * @test
     */
    public function it_hydrates_no_label_in_any_locale_as_none_so_the_target_name_shows(): void
    {
        $link = Links::handler()->hydrate(new Link(label: 'Link'), self::storeLink([]), $this->context('de_DE'));

        self::assertNull($link?->label);
        self::assertSame('Copenhagen', $link?->text());
    }

    /**
     * @test
     */
    public function it_picks_a_translatable_ref_of_the_current_locale_along_the_fallback_chain(): void
    {
        $handler = Links::handler();
        $value = ['target' => 'url', 'ref' => ['en_US' => 'https://example.com/en', 'de_DE' => 'https://example.com/de'], 'anchor' => null, 'label' => ['en_US' => 'Size guide']];

        self::assertSame('https://example.com/de', $handler->hydrate(new Link(label: 'Link'), $value, $this->context('de_DE'))?->resolved?->url);
        self::assertSame('https://example.com/en', $handler->hydrate(new Link(label: 'Link'), $value, $this->context('da_DK'))?->resolved?->url);
    }

    /**
     * @test
     */
    public function it_hydrates_one_link_for_every_locale(): void
    {
        $value = ['target' => 'app_store', 'ref' => 'aarhus', 'anchor' => null, 'label' => 'Our store'];

        $link = Links::handler()->hydrate(new Link(label: 'Link', translatable: false), $value, $this->context('de_DE'));

        self::assertSame('Our store', $link?->label);
        self::assertSame('/de_DE/stores/aarhus', $link?->resolved?->url);
    }

    /**
     * @test
     */
    public function it_hydrates_one_locale_s_whole_link(): void
    {
        $value = ['target' => 'url', 'ref' => 'mailto:shop@example.com', 'anchor' => null, 'label' => null];

        $link = Links::handler()->hydrate(new Link(label: 'Link', translatable: true), $value, $this->context('en_US'));

        self::assertSame('mailto:shop@example.com', $link?->resolved?->url);
        self::assertSame('shop@example.com', $link?->text());
    }

    /**
     * @test
     */
    public function it_adds_the_anchor_to_where_the_link_leads(): void
    {
        $value = ['target' => 'app_store', 'ref' => 'aarhus', 'anchor' => 'opening-hours', 'label' => []];

        $link = Links::handler()->hydrate(new Link(label: 'Link'), $value, $this->context('en_US'));

        self::assertSame('opening-hours', $link?->anchor);
        self::assertSame('/en_US/stores/aarhus#opening-hours', $link?->resolved?->url);
    }

    /**
     * @test
     */
    public function it_hydrates_a_link_that_does_not_resolve_with_nowhere_to_lead(): void
    {
        $link = Links::handler()->hydrate(new Link(label: 'Link'), ['target' => 'app_store', 'ref' => 'odense', 'label' => ['en_US' => 'Odense']], $this->context('en_US'));

        self::assertInstanceOf(LinkValue::class, $link);
        self::assertSame('Odense', $link->label);
        self::assertNull($link->resolved);
        self::assertNull($link->text());
    }

    /**
     * @test
     */
    public function it_resolves_a_link_to_a_record_from_the_records_loaded_before_rendering(): void
    {
        $references = new ReferenceMap(['product' => ['PANCAKES' => new Recipe('Pancakes', 'pancakes')]]);
        $handler = Links::handler();

        $link = $handler->hydrate(new Link(label: 'Link'), ['target' => 'recipe', 'ref' => 'PANCAKES', 'label' => []], $this->context('en_US', $references));
        self::assertEquals(new ResolvedLink('recipe', 'PANCAKES', '/en_US/recipes/pancakes', 'Pancakes'), $link?->resolved);

        $broken = $handler->hydrate(new Link(label: 'Link'), ['target' => 'recipe', 'ref' => 'WAFFLES', 'label' => []], $this->context('en_US', $references));
        self::assertNotNull($broken);
        self::assertNull($broken->resolved);
    }

    /**
     * @test
     */
    public function it_hydrates_a_link_to_a_target_the_field_does_not_link_to_or_that_does_not_exist_as_missing(): void
    {
        $handler = Links::handler();

        self::assertNull($handler->hydrate(new Link(label: 'Link', targets: ['url']), self::storeLink([]), $this->context('en_US')));
        self::assertNull($handler->hydrate(new Link(label: 'Link'), ['target' => 'media', 'ref' => '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e'], $this->context('en_US')));
    }

    /**
     * @test
     */
    public function it_hydrates_a_value_that_does_not_fit_or_has_no_ref_as_missing(): void
    {
        $handler = Links::handler();
        $field = new Link(label: 'Link');

        self::assertNull($handler->hydrate($field, 'https://example.com', $this->context('en_US')));
        self::assertNull($handler->hydrate($field, ['target' => 'url', 'ref' => 7], $this->context('en_US')));
        self::assertNull($handler->hydrate($field, ['target' => 'url', 'ref' => ''], $this->context('en_US')));
        self::assertNull($handler->hydrate($field, ['target' => 'url', 'ref' => ['de_DE' => '']], $this->context('en_US')));
        self::assertNull($handler->hydrate(new Link(label: 'Link', translatable: false), ['target' => 'url', 'ref' => ['en_US' => 'https://example.com']], $this->context('en_US')));
    }

    /**
     * @test
     */
    public function it_fits_a_link_in_its_shape(): void
    {
        $handler = Links::handler();

        self::assertTrue($handler->fits(new Link(label: 'Link'), self::storeLink(['en_US' => 'Visit us'])));
        self::assertTrue($handler->fits(new Link(label: 'Link'), ['target' => 'url', 'ref' => ['en_US' => 'https://example.com']]));
        self::assertTrue($handler->fits(new Link(label: 'Link', translatable: false), ['target' => 'url', 'ref' => 'https://example.com', 'anchor' => 'top', 'label' => 'Example']));
        self::assertFalse($handler->fits(new Link(label: 'Link', translatable: false), self::storeLink(['en_US' => 'Visit us'])));
        self::assertFalse($handler->fits(new Link(label: 'Link'), ['target' => 'url', 'ref' => 'https://example.com', 'label' => 'Example']));
        self::assertFalse($handler->fits(new Link(label: 'Link'), ['ref' => 'https://example.com']));
        self::assertFalse($handler->fits(new Link(label: 'Link'), ['target' => 'url', 'ref' => 'https://example.com', 'anchor' => 7]));
        self::assertFalse($handler->fits(new Link(label: 'Link'), ['https://example.com']));
    }

    /**
     * @test
     */
    public function a_link_without_a_ref_is_empty(): void
    {
        $handler = Links::handler();

        self::assertTrue($handler->isEmpty(new Link(label: 'Link'), ['target' => 'app_store', 'ref' => '', 'label' => ['en_US' => 'Visit us']]));
        self::assertTrue($handler->isEmpty(new Link(label: 'Link'), ['target' => 'url', 'ref' => ['en_US' => '']]));
        self::assertTrue($handler->isEmpty(new Link(label: 'Link'), ['target' => 'url']));
        self::assertFalse($handler->isEmpty(new Link(label: 'Link'), self::storeLink([])));
    }

    /**
     * @test
     */
    public function it_refers_to_the_record_a_link_to_a_record_refers_to_in_every_locale_as_a_link_not_a_dependency(): void
    {
        $handler = Links::handler();

        self::assertEquals([new Reference('product', 'PANCAKES', false)], [...$handler->references(new Link(label: 'Link'), ['target' => 'recipe', 'ref' => 'PANCAKES'])]);
        self::assertEquals(
            [new Reference('product', 'PANCAKES', false), new Reference('product', 'WAFFLES', false)],
            [...$handler->references(new Link(label: 'Link'), ['target' => 'recipe', 'ref' => ['en_US' => 'PANCAKES', 'de_DE' => 'WAFFLES', 'da_DK' => 'PANCAKES']])],
        );
    }

    /**
     * @test
     */
    public function it_refers_to_nothing_for_a_link_to_a_target_without_records_or_a_value_that_does_not_fit(): void
    {
        $handler = Links::handler();

        self::assertSame([], [...$handler->references(new Link(label: 'Link'), self::storeLink([]))]);
        self::assertSame([], [...$handler->references(new Link(label: 'Link'), ['target' => 'recipe', 'ref' => ['$bind' => 'product.url', '$params' => [], '$fallback' => null]])]);
        self::assertSame([], [...$handler->references(new Link(label: 'Link'), 'PANCAKES')]);
    }

    /**
     * @test
     */
    public function it_accepts_a_link_to_a_target_the_field_links_to(): void
    {
        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation(Argument::cetera())->shouldNotBeCalled();

        Links::handler()->validate(new Link(label: 'Link'), ['target' => 'url', 'ref' => ['en_US' => 'https://example.com', 'de_DE' => 'tel:+4512345678'], 'anchor' => 'top'], $context->reveal());
    }

    /**
     * @test
     */
    public function it_refuses_a_value_that_is_not_a_link(): void
    {
        $this->expectViolation('setono_sylius_cms.field.not_link', ['https://example.com']);
    }

    /**
     * @test
     */
    public function it_refuses_a_link_to_a_target_the_field_does_not_link_to(): void
    {
        $this->expectViolation('setono_sylius_cms.field.link_target_not_allowed', ['target' => 'app_store', 'ref' => 'aarhus'], new Link(label: 'Link', targets: ['url']));
    }

    /**
     * @test
     */
    public function it_refuses_a_url_a_link_cannot_lead_to_in_any_locale(): void
    {
        $this->expectViolation('setono_sylius_cms.field.not_link_url', ['target' => 'url', 'ref' => ['en_US' => 'https://example.com', 'de_DE' => 'javascript:alert(1)']]);
    }

    /**
     * @test
     */
    public function it_refuses_an_anchor_with_a_hash_or_a_space(): void
    {
        $this->expectViolation('setono_sylius_cms.field.not_link_anchor', ['target' => 'url', 'ref' => ['en_US' => '/faq'], 'anchor' => '#delivery times']);
    }

    /**
     * @param mixed $value
     */
    private function expectViolation(string $message, $value, ?Link $field = null): void
    {
        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->setParameter(Argument::cetera())->willReturn($builder->reveal());
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation($message)->willReturn($builder->reveal())->shouldBeCalledOnce();
        $context->buildViolation(Argument::not($message))->shouldNotBeCalled();

        Links::handler()->validate($field ?? new Link(label: 'Link'), $value, $context->reveal());
    }

    /**
     * @param array<string, string> $label
     *
     * @return array{target: string, ref: string, anchor: null, label: array<string, string>}
     */
    private static function storeLink(array $label): array
    {
        return ['target' => 'app_store', 'ref' => 'copenhagen', 'anchor' => null, 'label' => $label];
    }

    private function context(string $localeCode, ReferenceMap $references = new ReferenceMap()): HydrationContext
    {
        return new HydrationContext(
            new RenderContext($this->prophesize(ChannelInterface::class)->reveal(), $localeCode, 'USD', null, '@guest', new \DateTimeImmutable()),
            $references,
        );
    }
}
