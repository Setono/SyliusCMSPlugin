<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Context\ContextCheck;
use Setono\SyliusCMSPlugin\Context\ContextDependency;
use Setono\SyliusCMSPlugin\Context\ContextDependencyFinderInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Registry\ContextKeyRegistry;
use Setono\SyliusCMSPlugin\Validation\ValidationError;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\Translator;

/**
 * Publish refuses what needs context the entry doesn't provide, and a snippet's declared context can't change while
 * something depends on it. See "Snippets and their context" and "Mismatches" in docs/spec/slots.md
 */
final class ContextCheckTest extends TestCase
{
    private const NOTE = '0193a5be-0000-7000-8000-000000000001';

    private const HEADING = '0193a5be-0000-7000-8000-000000000002';

    /**
     * @test
     */
    public function it_finds_what_needs_context_an_entry_does_not_provide(): void
    {
        $check = self::check(
            self::block('Product note', ['product'], self::NOTE),
            self::block('Heading', [], self::HEADING),
            self::block('About the entry', ['entry'], self::HEADING),
        );

        self::assertEquals([
            new ValidationError(self::NOTE, null, null, 'The block "Product note" needs the host-page context "Product page".'),
        ], $check->mismatches(new Page()));
    }

    /**
     * @test
     */
    public function it_finds_what_needs_context_a_snippet_does_not_declare_and_where_it_is_placed_that_does_not_provide_what_it_declares(): void
    {
        $check = self::check(
            self::block('Product note', ['product'], self::NOTE),
            self::block('Taxon banner', ['taxon', 'brand'], self::HEADING),
            self::placement('Product page: under the price', ['product']),
            self::placement('Footer', []),
            self::placement('A slot the code no longer declares', null),
        );

        self::assertEquals([
            new ValidationError(self::HEADING, null, null, 'The block "Taxon banner" needs the host-page context "Taxon page", "brand".'),
            new ValidationError(null, 'context', null, 'The placement in "Footer" doesn\'t provide the host-page context "Product page".'),
        ], $check->mismatches(self::snippet(['product'])));
    }

    /**
     * @test
     */
    public function it_blocks_removing_a_context_key_that_something_needs(): void
    {
        $check = self::check(
            self::block('Product note', ['product'], self::NOTE),
            self::block('Taxon banner', ['taxon'], self::HEADING),
            self::placement('Product page: under the price', ['product'], needs: ['product']),
            self::placement('Product page: above the tabs', ['product']),
        );

        self::assertEquals([
            new ValidationError(null, 'context', null, 'The block "Product note" needs the host-page context "Product page".'),
            new ValidationError(null, 'context', null, 'The placement in "Product page: under the price" needs the host-page context "Product page".'),
        ], $check->blockers(self::snippet(['product', 'taxon']), ['product', 'taxon'], ['taxon']));
        self::assertSame([], $check->blockers(self::snippet(['product', 'taxon', 'brand']), ['product', 'taxon', 'brand'], ['product', 'taxon']), 'Nothing needs the brand');
    }

    /**
     * @test
     */
    public function it_blocks_adding_a_context_key_that_a_placement_s_slot_does_not_provide(): void
    {
        $check = self::check(
            self::block('Product note', ['product'], self::NOTE),
            self::placement('Product page: under the price', ['product']),
            self::placement('Footer', []),
            self::placement('A slot the code no longer declares', null),
        );

        self::assertEquals([
            new ValidationError(null, 'context', null, 'The placement in "Footer" doesn\'t provide the host-page context "Product page".'),
        ], $check->blockers(self::snippet([]), [], ['product']));
        self::assertEquals([
            new ValidationError(null, 'context', null, 'The placement in "Product page: under the price" doesn\'t provide the host-page context "Taxon page".'),
            new ValidationError(null, 'context', null, 'The placement in "Footer" doesn\'t provide the host-page context "Taxon page".'),
        ], $check->blockers(self::snippet(['product']), ['product'], ['product', 'taxon']));
    }

    /**
     * Something that already doesn't fit, such as after an import, doesn't block a change that has nothing to do with it
     *
     * @test
     */
    public function it_blocks_a_change_only_by_what_depends_on_the_keys_it_removes_or_adds(): void
    {
        $check = self::check(
            self::block('Taxon banner', ['taxon'], self::HEADING),
            self::placement('Product page: under the price', ['product']),
        );

        self::assertSame([], $check->blockers(self::snippet(['product', 'brand']), ['product', 'brand'], ['product']));
        self::assertSame([], $check->blockers(self::snippet(['product']), ['product'], ['product']));
    }

    private static function check(ContextDependency ...$dependencies): ContextCheck
    {
        $finder = new class($dependencies) implements ContextDependencyFinderInterface {
            /**
             * @param list<ContextDependency> $dependencies
             */
            public function __construct(private readonly array $dependencies)
            {
            }

            public function findIn(EntryInterface $workingCopy): iterable
            {
                return $this->dependencies;
            }
        };

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'setono_sylius_cms.context_key.product' => 'Product page',
            'setono_sylius_cms.context_key.taxon' => 'Taxon page',
        ], 'en');
        $translator->addResource('array', [
            'setono_sylius_cms.context.needs' => '%what% needs the host-page context %context%.',
            'setono_sylius_cms.context.does_not_provide' => '%what% doesn\'t provide the host-page context %context%.',
            'setono_sylius_cms.context.block' => 'The block "%block%"',
            'setono_sylius_cms.context.placement' => 'The placement in "%slot%"',
        ], 'en', 'validators');

        return new ContextCheck(
            [$finder],
            new ContextKeyRegistry([
                'product' => ['class' => ProductInterface::class, 'label' => 'setono_sylius_cms.context_key.product'],
                'taxon' => ['class' => TaxonInterface::class, 'label' => 'setono_sylius_cms.context_key.taxon'],
            ]),
            $translator,
        );
    }

    /**
     * @param list<string> $needs
     */
    private static function block(string $label, array $needs, string $key): ContextDependency
    {
        return new ContextDependency(new TranslatableMessage('setono_sylius_cms.context.block', ['%block%' => $label], 'validators'), $needs, block: $key);
    }

    /**
     * @param list<string>|null $provides
     * @param list<string> $needs
     */
    private static function placement(string $slot, ?array $provides, array $needs = []): ContextDependency
    {
        return new ContextDependency(new TranslatableMessage('setono_sylius_cms.context.placement', ['%slot%' => $slot], 'validators'), $needs, $provides);
    }

    /**
     * @param list<string> $context
     */
    private static function snippet(array $context): Snippet
    {
        $snippet = new Snippet();
        $snippet->setContext($context);

        return $snippet;
    }
}
