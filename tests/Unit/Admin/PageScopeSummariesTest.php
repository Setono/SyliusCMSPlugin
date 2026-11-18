<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Admin\PageScopeCondition;
use Setono\SyliusCMSPlugin\Admin\PageScopeSummaries;
use Setono\SyliusCMSPlugin\Builder\Search\SearchInterface;
use Setono\SyliusCMSPlugin\Builder\Search\SearchResult;
use Setono\SyliusCMSPlugin\Condition\Kind\ProductInTaxonKind;
use Setono\SyliusCMSPlugin\Condition\Kind\ProductKind;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\ListFieldHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Model\Placement;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Support\Sources;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\LocaleIs;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

/**
 * How the Placements section of a snippet's overview page shows each placement's page scope: each condition by its
 * kind, "is one of" or "is not one of", and what it names. See "Placements" in docs/spec/admin.md
 */
final class PageScopeSummariesTest extends TestCase
{
    /**
     * @test
     */
    public function it_names_each_condition_s_kind_how_it_matches_and_the_products_and_taxons_it_names_in_the_editor_s_order(): void
    {
        $placement = new Placement(new Snippet(), 'product.after_price', 0);
        $placement->setPageScope([
            ['kind' => 'product_in_taxon', 'negate' => false, 'params' => ['taxons' => ['SHOES', 'BOOTS'], 'includeChildren' => true]],
            ['kind' => 'product', 'negate' => true, 'params' => ['products' => ['GONE', 'SNEAKERS']]],
        ]);
        $everywhere = new Placement(new Snippet(), 'shop.footer', 0);

        self::assertEquals([
            $placement->getUuid() => [
                new PageScopeCondition('product_in_taxon', 'Product in taxon', 'is one of', ['Shoes', 'Boots', 'Include the taxons below them']),
                // A deleted product by its code
                new PageScopeCondition('product', 'Product', 'is not one of', ['GONE', 'Sneakers']),
            ],
            $everywhere->getUuid() => [],
        ], $this->summaries()->of([$placement, $everywhere]));
    }

    /**
     * @test
     */
    public function it_names_a_condition_of_a_kind_the_code_no_longer_has_by_its_code_and_leaves_out_what_is_not_a_condition(): void
    {
        $placement = new Placement(new Snippet(), 'product.after_price', 0);
        $placement->setPageScope([
            ['kind' => 'brand', 'negate' => false, 'params' => ['brands' => ['ACME']]],
            ['negate' => false],
            ['kind' => 'product_in_taxon', 'negate' => false, 'params' => ['taxons' => ['SHOES'], 'includeChildren' => false]],
        ]);

        self::assertEquals([$placement->getUuid() => [
            new PageScopeCondition('brand', null, null, []),
            new PageScopeCondition('product_in_taxon', 'Product in taxon', 'is one of', ['Shoes']),
        ]], $this->summaries()->of([$placement]));
    }

    /**
     * A kind that matches otherwise words it its own way, and a parameter that refers to nothing shows as it's stored
     *
     * @test
     */
    public function it_words_a_condition_as_its_kind_does_and_shows_other_values_as_they_are(): void
    {
        $placement = new Placement(new Snippet(), 'shop.footer', 0);
        $placement->setPageScope([['kind' => 'locale_is', 'negate' => true, 'params' => ['locales' => ['da_DK', 'de_DE']]]]);

        self::assertEquals([$placement->getUuid() => [
            new PageScopeCondition('locale_is', 'Locale is', 'is not one of', ['da_DK', 'de_DE']),
        ]], $this->summaries()->of([$placement]));
    }

    private function summaries(): PageScopeSummaries
    {
        $translator = new Translator('en_US');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'setono_sylius_cms.condition_kind.product' => 'Product',
            'setono_sylius_cms.condition_kind.product_in_taxon' => 'Product in taxon',
            'setono_sylius_cms.field.product_in_taxon_condition.include_children' => 'Include the taxons below them',
            'setono_sylius_cms.builder.is_one_of' => 'is one of',
            'setono_sylius_cms.builder.is_not_one_of' => 'is not one of',
        ], 'en_US');

        $handlers = new FieldHandlerLocator(new ServiceLocator([
            ListFieldHandler::class => static fn (): ListFieldHandler => Sources::listFieldHandler(),
            ToggleHandler::class => static fn (): ToggleHandler => new ToggleHandler(),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler($translator),
        ]));

        return new PageScopeSummaries(
            Conditions::registry(['product' => new ProductKind(), 'product_in_taxon' => new ProductInTaxonKind(), 'locale_is' => new LocaleIs()]),
            $handlers,
            new ServiceLocator([
                'product' => static fn (): SearchInterface => self::search(['SNEAKERS' => 'Sneakers']),
                'taxon' => static fn (): SearchInterface => self::search(['SHOES' => 'Shoes', 'BOOTS' => 'Boots']),
            ]),
            $translator,
        );
    }

    /**
     * @param array<string, string> $names ref => name of what exists
     */
    private static function search(array $names): SearchInterface
    {
        return new class($names) implements SearchInterface {
            /**
             * @param array<string, string> $names
             */
            public function __construct(private readonly array $names)
            {
            }

            public function search(string $query, string $localeCode): array
            {
                return [];
            }

            public function find(array $refs, string $localeCode): array
            {
                TestCase::assertSame('en_US', $localeCode);

                $found = [];
                foreach ($refs as $ref) {
                    if (isset($this->names[$ref])) {
                        $found[] = new SearchResult($ref, $this->names[$ref]);
                    }
                }

                return $found;
            }
        };
    }
}
