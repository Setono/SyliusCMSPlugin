<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem\Kind;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Column;
use Setono\SyliusCMSPlugin\Block\Columns;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Condition\Kind\ChannelKind;
use Setono\SyliusCMSPlugin\Condition\Kind\CustomerGroupKind;
use Setono\SyliusCMSPlugin\Condition\Kind\LocaleKind;
use Setono\SyliusCMSPlugin\Condition\Kind\ProductKind;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\ListFieldHandler;
use Setono\SyliusCMSPlugin\Field\ProductHandler;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Problem\BrokenReferences;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Kind\BrokenConditionReference;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Rendering\ReferenceTypeLoaderInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Support\Sources;
use Setono\SyliusCMSPlugin\Tests\Unit\Condition\Fixture\ProductAvailable;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Locale\Model\Locale;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A condition naming a channel or locale the store no longer has, or with a parameter referring to a deleted record,
 * such as a product, is a broken reference (ADR 0002). See "Deleted customer groups" in docs/spec/targeting.md and
 * "Problems" in docs/spec/admin.md
 */
final class BrokenConditionReferenceTest extends TestCase
{
    use ProphecyTrait;

    private const A = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const B = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    private const C = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4c';

    /**
     * @test
     */
    public function it_finds_a_deleted_channel_or_locale_named_in_a_condition_at_any_depth_as_an_error_fixed_in_the_builder_at_the_block(): void
    {
        $page = self::page(
            new StoredBlock(self::A, 'heading', 1, conditions: [
                self::condition(ChannelKind::CODE, ['channels' => ['WEB', 'OUTLET']]),
                self::condition(LocaleKind::CODE, ['locales' => ['en_US', 'sv_SE', 'da_DK']], negate: true),
            ]),
            new StoredBlock(self::B, 'columns', 1, children: [
                new StoredBlock(self::C, 'column', 1, conditions: [self::condition(ChannelKind::CODE, ['channels' => ['OUTLET', 'OUTLET']])]),
            ]),
        );

        self::assertEquals([
            self::deleted($page, self::A, 'channel', 'OUTLET', 'setono_sylius_cms.block_type.heading'),
            self::deleted($page, self::A, 'locale', 'sv_SE', 'setono_sylius_cms.block_type.heading'),
            self::deleted($page, self::C, 'channel', 'OUTLET', 'setono_sylius_cms.block_type.column'),
        ], [...$this->kind()->findIn($page)]);
    }

    /**
     * @test
     */
    public function it_finds_a_condition_parameter_referring_to_a_deleted_record_with_one_query_per_reference_type(): void
    {
        $page = self::page(
            new StoredBlock(self::A, 'heading', 1, conditions: [self::condition(ProductKind::CODE, ['products' => ['MUG', 'HAT']])]),
            new StoredBlock(self::B, 'heading', 1, conditions: [self::condition('product_available', ['product' => 'CAP'])]),
        );
        $products = $this->prophesize(ReferenceTypeLoaderInterface::class);
        $products->existing(['MUG', 'HAT', 'CAP'])->willReturn(['MUG'])->shouldBeCalledOnce();

        self::assertEquals([
            self::broken($page, self::A, 'setono_sylius_cms.field.product_condition.products'),
            self::broken($page, self::B, 'Product'),
        ], [...$this->kind(products: $products->reveal())->findIn($page)]);
    }

    /**
     * A condition of a kind the code no longer has, and a block of a type it no longer has, are other kinds' problems, a
     * deleted customer group is DeletedCustomerGroup's, and a reference type without a loader can't be looked up
     *
     * @test
     */
    public function it_finds_nothing_in_conditions_it_cannot_read_or_that_another_kind_reads_or_that_hold_only_what_exists(): void
    {
        $page = self::page(
            new StoredBlock(self::A, 'promo', 1, conditions: [self::condition(ChannelKind::CODE, ['channels' => ['OUTLET']])], children: [
                new StoredBlock(self::B, 'heading', 1, conditions: [self::condition(ChannelKind::CODE, ['channels' => ['OUTLET']])]),
            ]),
            new StoredBlock(self::C, 'heading', 1, conditions: [
                self::condition('brand', ['channels' => ['OUTLET']]),
                ['negate' => false, 'params' => []],
                self::condition(CustomerGroupKind::CODE, ['groups' => ['gone']]),
                self::condition(ChannelKind::CODE, ['channels' => 'OUTLET']),
                self::condition(ChannelKind::CODE, ['channels' => ['WEB']]),
                self::condition(ProductKind::CODE, []),
                self::condition('signed_in', []),
            ]),
        );
        $products = $this->prophesize(ReferenceTypeLoaderInterface::class);
        $products->existing(Argument::cetera())->shouldNotBeCalled();

        self::assertSame([], [...$this->kind(products: $products->reveal())->findIn($page)]);
    }

    /**
     * @test
     */
    public function it_reads_the_stores_channels_and_locales_once_and_again_after_it_was_asked_about_a_write(): void
    {
        $channels = $this->prophesize(RepositoryInterface::class);
        $channels->findAll()->willReturn([self::channel('WEB')])->shouldBeCalledTimes(2);
        $locales = $this->prophesize(RepositoryInterface::class);
        $locales->findAll()->willReturn([self::locale('en_US')])->shouldBeCalledTimes(2);
        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findReferencing(['OUTLET'])->willReturn([]);
        $kind = $this->kind($entries->reveal(), channels: $channels->reveal(), locales: $locales->reveal());
        $page = self::page(new StoredBlock(self::A, 'heading', 1, conditions: [
            self::condition(ChannelKind::CODE, ['channels' => ['WEB']]),
            self::condition(LocaleKind::CODE, ['locales' => ['en_US']]),
        ]));

        [...$kind->findIn($page)];
        [...$kind->findIn($page)];
        [...$kind->findEntriesReferencing([self::channel('OUTLET')])];
        [...$kind->findIn($page)];
    }

    /**
     * A channel, locale, product, taxon, entry or media item deleted, or given another code or UUID, has the entries that
     * may name it counted again, by its new code and its former one
     *
     * @test
     */
    public function it_finds_the_entries_that_may_name_what_was_written_by_its_code_or_uuid_and_its_former_one(): void
    {
        $page = new Page();
        $linked = new Page();
        $product = new Product();
        $product->setCode('MUG');
        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findReferencing(['OLD', 'OUTLET', 'sv_SE', 'MUG', $linked->getUuid()])->willReturn([$page]);

        self::assertSame([$page], [...$this->kind($entries->reveal())->findEntriesReferencing([self::channel('OUTLET'), self::locale('sv_SE'), $product, $linked, new \stdClass()], ['OLD'])]);
    }

    /**
     * @test
     */
    public function it_finds_no_entry_without_a_code(): void
    {
        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findReferencing(Argument::cetera())->shouldNotBeCalled();

        self::assertSame([], [...$this->kind($entries->reveal())->findEntriesReferencing([new Channel(), new \stdClass()])]);
    }

    /**
     * @test
     */
    public function it_reads_channels_locales_and_what_condition_parameters_refer_to_by_their_codes_and_uuids(): void
    {
        self::assertSame(
            [ChannelInterface::class, LocaleInterface::class, MediaItemInterface::class, EntryInterface::class, ProductInterface::class, TaxonInterface::class],
            BrokenConditionReference::getReferencedClasses(),
        );
        self::assertSame(
            [ChannelInterface::class => 'code', LocaleInterface::class => 'code', MediaItemInterface::class => 'uuid', EntryInterface::class => 'uuid', ProductInterface::class => 'code', TaxonInterface::class => 'code'],
            BrokenConditionReference::getReferenceFields(),
        );
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private static function condition(string $kind, array $params, bool $negate = false): array
    {
        return ['kind' => $kind, 'negate' => $negate, 'params' => $params];
    }

    private static function deleted(Page $page, string $block, string $type, string $code, string $blockLabel): Problem
    {
        return new Problem(
            'broken_condition_reference',
            Severity::Error,
            $page,
            new TranslatableMessage(sprintf('setono_sylius_cms.problem.deleted_%s_in_condition', $type), ['%code%' => $code, '%block%' => new TranslatableMessage($blockLabel)]),
            Fix::inBuilder($page, $block),
            $block,
        );
    }

    private static function broken(Page $page, string $block, string $fieldLabel): Problem
    {
        return new Problem(
            'broken_condition_reference',
            Severity::Error,
            $page,
            new TranslatableMessage('setono_sylius_cms.problem.broken_condition_reference', [
                '%field%' => new TranslatableMessage($fieldLabel),
                '%block%' => new TranslatableMessage('setono_sylius_cms.block_type.heading'),
                '%type%' => new TranslatableMessage('setono_sylius_cms.reference_type.product'),
            ]),
            Fix::inBuilder($page, $block),
            $block,
        );
    }

    private static function page(StoredBlock ...$blocks): Page
    {
        $page = new Page();
        $page->setBlockTree(new StoredTree(null, array_values($blocks)));

        return $page;
    }

    private static function channel(string $code): Channel
    {
        $channel = new Channel();
        $channel->setCode($code);

        return $channel;
    }

    private static function locale(string $code): Locale
    {
        $locale = new Locale();
        $locale->setCode($code);

        return $locale;
    }

    /**
     * @param RepositoryInterface<ChannelInterface>|null $channels
     * @param RepositoryInterface<LocaleInterface>|null $locales
     */
    private function kind(
        ?EntryRepositoryInterface $entries = null,
        ?ReferenceTypeLoaderInterface $products = null,
        ?RepositoryInterface $channels = null,
        ?RepositoryInterface $locales = null,
    ): BrokenConditionReference {
        if (null === $channels) {
            $channelRepository = $this->prophesize(RepositoryInterface::class);
            $channelRepository->findAll()->willReturn([self::channel('WEB')]);
            $channels = $channelRepository->reveal();
        }

        if (null === $locales) {
            $localeRepository = $this->prophesize(RepositoryInterface::class);
            $localeRepository->findAll()->willReturn([self::locale('en_US'), self::locale('da_DK')]);
            $locales = $localeRepository->reveal();
        }

        $products ??= $this->prophesize(ReferenceTypeLoaderInterface::class)->reveal();

        $blockTypes = new BlockTypeRegistry(['heading' => Heading::class, 'columns' => Columns::class, 'column' => Column::class], new FieldReader());

        return new BrokenConditionReference(
            new TreeValues($blockTypes, new LayoutRegistry([], new FieldReader())),
            $blockTypes,
            Conditions::registry([
                ChannelKind::CODE => new ChannelKind(),
                LocaleKind::CODE => new LocaleKind(),
                CustomerGroupKind::CODE => new CustomerGroupKind(),
                ProductKind::CODE => new ProductKind(),
                'product_available' => new ProductAvailable(),
            ]),
            new FieldHandlerLocator(new ServiceLocator([
                ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler(new IdentityTranslator()),
                ListFieldHandler::class => static fn (): ListFieldHandler => Sources::listFieldHandler(),
                ProductHandler::class => static fn (): ProductHandler => new ProductHandler(),
            ])),
            new BrokenReferences(
                new ServiceLocator(['product' => static fn (): ReferenceTypeLoaderInterface => $products]),
                $entries ?? $this->prophesize(EntryRepositoryInterface::class)->reveal(),
            ),
            $channels,
            $locales,
        );
    }
}
