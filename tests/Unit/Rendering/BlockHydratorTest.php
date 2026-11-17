<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\NumberHandler;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Localization\FallbackChain;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Rendering\BlockHydrator;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Tests\Support\Bindings;
use Setono\SyliusCMSPlugin\Tests\Support\Rendering;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\ProductBrand;
use Setono\SyliusCMSPlugin\Tests\Unit\Binding\Fixture\StockLevel;
use Setono\SyliusCMSPlugin\Tests\Unit\Rendering\Fixture\StockNote;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Sylius\Component\Core\Model\Product;
use Sylius\Resource\Translation\Provider\ImmutableTranslationLocaleProvider;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Hydration resolves a binding from the host-page context, the channel or the customer before the fallback chain: an
 * empty value shows the binding's fallback, which follows the field's translatability, and without one the field is
 * empty. See "Hydration" in docs/spec/rendering.md and "Bindings" in docs/spec/slots.md
 */
final class BlockHydratorTest extends TestCase
{
    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    /**
     * @test
     */
    public function it_shows_the_bound_property_of_the_host_page_s_object(): void
    {
        $note = $this->hydrate(['text' => self::binding('product.brand')], new ProductBrand('Acme'), self::onProductPage());

        self::assertSame('Acme', $note->text);
    }

    /**
     * @test
     */
    public function it_shows_a_bound_number_in_a_number_field_and_as_text_in_a_text_field(): void
    {
        $note = $this->hydrate(['count' => self::binding('product.stock_level'), 'code' => self::binding('product.stock_level')], new StockLevel(), self::onProductPage());

        self::assertSame(7, $note->count);
        self::assertSame('7', $note->code);
    }

    /**
     * @test
     */
    public function it_shows_the_fallback_of_a_translatable_field_in_the_current_locale_when_the_bound_value_is_empty(): void
    {
        $fallback = ['en_US' => 'our brand', 'da_DK' => 'vores mærke'];

        $note = $this->hydrate(['text' => self::binding('product.brand', $fallback)], new ProductBrand(''), self::onProductPage('da_DK'));

        self::assertSame('vores mærke', $note->text);
    }

    /**
     * @test
     */
    public function it_falls_back_along_the_locale_s_fallback_chain_for_a_fallback_the_locale_does_not_have(): void
    {
        $note = $this->hydrate(['text' => self::binding('product.brand', ['en_US' => 'our brand'])], new ProductBrand(), Rendering::context(localeCode: 'da_DK'));

        self::assertSame('our brand', $note->text);
    }

    /**
     * @test
     */
    public function it_shows_the_fallback_of_a_shared_field_where_the_page_does_not_have_the_context_key_s_object(): void
    {
        $note = $this->hydrate(['count' => self::binding('product.stock_level', 3)], new StockLevel(), Rendering::context());

        self::assertSame(3, $note->count);
    }

    /**
     * @test
     */
    public function it_leaves_the_field_empty_without_a_fallback(): void
    {
        $note = $this->hydrate(['text' => self::binding('product.brand'), 'count' => self::binding('product.stock_level')], new ProductBrand(), Rendering::context());

        self::assertNull($note->text);
        self::assertNull($note->count);
    }

    /**
     * @test
     */
    public function it_shows_the_fallback_for_a_property_of_a_type_the_field_does_not_accept(): void
    {
        $note = $this->hydrate(['count' => self::binding('product.brand', 4)], new ProductBrand(), self::onProductPage());

        self::assertSame(4, $note->count);
    }

    /**
     * @test
     */
    public function it_leaves_a_binding_of_another_shape_empty(): void
    {
        $note = $this->hydrate(['text' => ['$bind' => 'product.brand', '$fallback' => ['en_US' => 'our brand']]], new ProductBrand(), self::onProductPage());

        self::assertNull($note->text);
    }

    /**
     * @test
     */
    public function it_never_reads_a_field_that_can_not_be_bound_as_a_binding(): void
    {
        $note = $this->hydrate(['highlighted' => self::binding('product.brand', true)], new ProductBrand(), self::onProductPage());

        self::assertFalse($note->highlighted);
    }

    /**
     * @return array{'$bind': string, '$params': array<string, mixed>, '$fallback': mixed}
     */
    private static function binding(string $property, mixed $fallback = null): array
    {
        return ['$bind' => $property, '$params' => [], '$fallback' => $fallback];
    }

    private static function onProductPage(string $localeCode = 'en_US'): RenderContext
    {
        return Rendering::context(localeCode: $localeCode, hostPageContext: ['product' => new Product()]);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function hydrate(array $fields, object $property, RenderContext $context): StockNote
    {
        $handlers = new FieldHandlerLocator(new ServiceLocator([
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            NumberHandler::class => static fn (): NumberHandler => new NumberHandler(),
            ToggleHandler::class => static fn (): ToggleHandler => new ToggleHandler(),
        ]));
        $fallbackChain = new FallbackChain(new ImmutableTranslationLocaleProvider(['en_US', 'da_DK'], 'en_US'));
        $properties = $property instanceof StockLevel ? [$property] : [$property, new StockLevel()];
        $hydrator = new BlockHydrator($handlers, new FallbackValues($handlers, $fallbackChain), Bindings::resolver($properties));

        $block = new StoredBlock(self::KEY, 'stock_note', 1);
        $block->fields = $fields;

        $note = $hydrator->hydrate((new BlockTypeRegistry(['stock_note' => StockNote::class], new FieldReader()))->get('stock_note'), $block, $context, new ReferenceMap());
        self::assertInstanceOf(StockNote::class, $note);

        return $note;
    }
}
