<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Problem;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\Spacer;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Problem\Kind\MisfitValue;
use Setono\SyliusCMSPlugin\Problem\Kind\UnknownBlockType;
use Setono\SyliusCMSPlugin\Problem\ProblemCountFingerprint;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ConditionKindMetadata;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistry;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Source\SourceInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Support\Sources;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Landing;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\RecipeLanding;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Standard;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\Clearance;
use Setono\SyliusCMSPlugin\Tests\Unit\Source\Fixture\OnSale;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class ProblemCountFingerprintTest extends TestCase
{
    use ProphecyTrait;

    private const KINDS = [UnknownBlockType::class, MisfitValue::class];

    private const BLOCK_TYPES = ['heading' => Heading::class, 'spacer' => Spacer::class];

    private const CONTENT_TYPES = [
        'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
        'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
    ];

    private const LAYOUTS = ['page' => ['standard' => Standard::class, 'landing' => Landing::class], 'recipe' => ['landing' => RecipeLanding::class]];

    /**
     * @test
     */
    public function it_is_the_same_for_the_same_code_and_fits_the_column_it_is_stored_in(): void
    {
        $fingerprint = self::fingerprint()->get();

        self::assertSame($fingerprint, self::fingerprint()->get());
        self::assertMatchesRegularExpression('/^[0-9a-f]{1,64}$/', $fingerprint);
    }

    /**
     * @test
     *
     * @dataProvider provideChangedCode
     */
    public function it_changes_with_the_code_the_counts_depend_on(ProblemCountFingerprint $changed): void
    {
        self::assertNotSame(self::fingerprint()->get(), $changed->get());
    }

    /**
     * @return iterable<string, array{ProblemCountFingerprint}>
     */
    public function provideChangedCode(): iterable
    {
        yield 'a problem kind added' => [self::fingerprint(kinds: [...self::KINDS, self::class])];
        yield 'a block type removed' => [self::fingerprint(blockTypes: ['heading' => Heading::class])];
        yield 'a block type switched off' => [self::fingerprint(disabledBlockTypes: ['spacer'])];
        yield 'a content type removed' => [self::fingerprint(contentTypes: ['page' => self::CONTENT_TYPES['page']])];
        yield 'a layout of the first content type removed' => [self::fingerprint(layouts: ['page' => ['standard' => Standard::class], 'recipe' => self::LAYOUTS['recipe']])];
        yield 'a layout of the second content type removed' => [self::fingerprint(layouts: ['page' => self::LAYOUTS['page']])];
        yield 'a layout switched off' => [self::fingerprint(disabledLayouts: ['page.landing'])];
        yield 'the condition kinds removed' => [self::fingerprint(conditionKinds: new ConditionKindRegistry([], new FieldReader(), new ServiceLocator([])))];
        yield 'a source removed' => [self::fingerprint(sources: ['clearance' => new Clearance()])];
        yield 'a source switched off' => [self::fingerprint(disabledSources: ['clearance'])];
    }

    /**
     * @test
     */
    public function it_reads_what_a_condition_kind_declares_but_not_its_service(): void
    {
        $kind = $this->prophesize(ConditionKindInterface::class)->reveal();
        $conditionKinds = $this->prophesize(ConditionKindRegistryInterface::class);
        $conditionKinds->all()->willReturn([new ConditionKindMetadata('signed_in', 'Signed in', [], null, null, [], $kind)]);
        $otherParams = $this->prophesize(ConditionKindRegistryInterface::class);
        $otherParams->all()->willReturn([new ConditionKindMetadata('signed_in', 'Signed in', [], null, \stdClass::class, [], $kind)]);
        $otherCode = $this->prophesize(ConditionKindRegistryInterface::class);
        $otherCode->all()->willReturn([new ConditionKindMetadata('logged_in', 'Signed in', [], null, null, [], $kind)]);

        $fingerprint = self::fingerprint(conditionKinds: $conditionKinds->reveal())->get();

        self::assertNotSame($fingerprint, self::fingerprint(conditionKinds: $otherParams->reveal())->get());
        self::assertNotSame($fingerprint, self::fingerprint(conditionKinds: $otherCode->reveal())->get());
    }

    /**
     * @test
     */
    public function it_reads_the_code_once_a_request(): void
    {
        $blockTypes = $this->prophesize(BlockTypeRegistryInterface::class);
        $blockTypes->all()->willReturn([]);
        $fingerprint = self::fingerprint(blockTypes: $blockTypes->reveal());

        $blockTypes->all()->shouldBeCalledOnce();
        $first = $fingerprint->get();
        self::assertSame($first, $fingerprint->get());
        $blockTypes->checkProphecyMethodsPredictions();

        $fingerprint->reset();
        $blockTypes->all()->shouldBeCalledTimes(2);
        self::assertSame($first, $fingerprint->get());
    }

    /**
     * @param list<class-string> $kinds
     * @param array<string, class-string>|BlockTypeRegistryInterface $blockTypes
     * @param list<string> $disabledBlockTypes
     * @param array<string, array{model: class-string<EntryInterface>, translation: class-string|null, resource: string}> $contentTypes
     * @param array<string, array<string, class-string>> $layouts
     * @param list<string> $disabledLayouts
     * @param array<string, SourceInterface>|null $sources
     * @param list<string> $disabledSources
     */
    private static function fingerprint(
        array $kinds = self::KINDS,
        array|BlockTypeRegistryInterface $blockTypes = self::BLOCK_TYPES,
        array $disabledBlockTypes = [],
        array $contentTypes = self::CONTENT_TYPES,
        array $layouts = self::LAYOUTS,
        array $disabledLayouts = [],
        ?ConditionKindRegistryInterface $conditionKinds = null,
        ?array $sources = null,
        array $disabledSources = [],
    ): ProblemCountFingerprint {
        return new ProblemCountFingerprint(
            $kinds,
            is_array($blockTypes) ? new BlockTypeRegistry($blockTypes, new FieldReader(), $disabledBlockTypes) : $blockTypes,
            new ContentTypeRegistry($contentTypes),
            new LayoutRegistry($layouts, new FieldReader(), disabled: $disabledLayouts),
            $conditionKinds ?? Conditions::registry(),
            Sources::registry($sources ?? ['on_sale' => new OnSale(), 'clearance' => new Clearance()], $disabledSources),
        );
    }
}
