<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Translation;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSet;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSetApplier;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetEntryTranslated;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetEntryValue;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetTranslated;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetValue;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Publishing\AutosaveInterface;
use Setono\SyliusCMSPlugin\Publishing\LoadedWorkingCopy;
use Setono\SyliusCMSPlugin\Publishing\SaveResult;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Translation\Fingerprint;
use Setono\SyliusCMSPlugin\Translation\TranslatableValue;
use Setono\SyliusCMSPlugin\Translation\TranslatableValues;
use Setono\SyliusCMSPlugin\Translation\TranslationState;
use Setono\SyliusCMSPlugin\Translation\ValueState;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;

final class TranslationStateTest extends TestCase
{
    use ProphecyTrait;

    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    /**
     * @test
     */
    public function it_gives_the_values_of_the_entry_s_working_copy(): void
    {
        $entry = self::page('Hello');
        $workingCopy = self::page('Hello there');
        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopy($entry)->willReturn($workingCopy);

        $state = new TranslationState($workingCopies->reveal(), $this->prophesize(AutosaveInterface::class)->reveal(), self::translatableValues());

        self::assertEquals(
            [new TranslatableValue('block.' . self::KEY . '.text', ValueState::Untranslated, 'en_US', 'Hello there', Fingerprint::of('Hello there'), null)],
            array_values(array_filter([...$state->values($entry, 'da_DK')], static fn (TranslatableValue $value): bool => str_starts_with($value->path, 'block.'))),
        );
    }

    /**
     * @test
     */
    public function it_writes_a_block_value_and_where_it_was_translated_from_on_top_of_what_editors_saved(): void
    {
        $entry = self::page('Hello');
        $fingerprint = Fingerprint::of('Hello');
        $autosave = $this->prophesize(AutosaveInterface::class);
        $autosave->load($entry)->willReturn(new LoadedWorkingCopy($entry, 7));
        $autosave->save($entry, new ChangeSet([
            new SetValue(self::KEY, 'text', 'da_DK', 'Hej'),
            new SetTranslated(self::KEY, 'text', 'da_DK', ['from' => 'en_US', 'fingerprint' => $fingerprint]),
        ]), 7)->shouldBeCalledOnce()->willReturn(new SaveResult(new LoadedWorkingCopy($entry, 8), false));

        $state = new TranslationState($this->prophesize(WorkingCopyProviderInterface::class)->reveal(), $autosave->reveal(), self::translatableValues());
        $state->write($entry, 'da_DK', 'block.' . self::KEY . '.text', 'Hej', 'en_US', $fingerprint);
    }

    /**
     * @test
     */
    public function it_writes_an_entry_value_and_where_it_was_translated_from(): void
    {
        $entry = self::page('Hello');
        $fingerprint = Fingerprint::of('About us');
        $autosave = $this->prophesize(AutosaveInterface::class);
        $autosave->load($entry)->willReturn(new LoadedWorkingCopy($entry, 0));
        $autosave->save($entry, new ChangeSet([
            new SetEntryValue('title', 'da_DK', 'Om os'),
            new SetEntryTranslated('title', 'da_DK', ['from' => 'en_US', 'fingerprint' => $fingerprint]),
        ]), 0)->shouldBeCalledOnce()->willReturn(new SaveResult(new LoadedWorkingCopy($entry, 1), false));

        $state = new TranslationState($this->prophesize(WorkingCopyProviderInterface::class)->reveal(), $autosave->reveal(), self::translatableValues());
        $state->write($entry, 'da_DK', 'entry.title', 'Om os', 'en_US', $fingerprint);
    }

    /**
     * A translator passes the fingerprint of the source it read, so a source that changed while the translation was in
     * flight leaves the value outdated
     *
     * @test
     */
    public function it_leaves_a_value_written_with_an_old_fingerprint_outdated(): void
    {
        $entry = self::page('Hello');
        $state = new TranslationState(self::ownWorkingCopy(), self::applyingAutosave(), self::translatableValues());
        $read = self::blockValue($state->values($entry, 'da_DK'));
        self::assertSame(ValueState::Untranslated, $read->state);
        self::assertNotNull($read->sourceLocaleCode);
        self::assertNotNull($read->sourceFingerprint);

        $entry->getBlockTree()->blocks[0]->setValue('text', 'en_US', 'Hello there');
        $state->write($entry, 'da_DK', 'block.' . self::KEY . '.text', 'Hej', $read->sourceLocaleCode, $read->sourceFingerprint);

        $written = self::blockValue($state->values($entry, 'da_DK'));
        self::assertSame(ValueState::Outdated, $written->state);
        self::assertSame('Hello there', $written->sourceValue);
        self::assertSame('Hej', $written->value);

        $state->write($entry, 'da_DK', 'block.' . self::KEY . '.text', 'Hej der', 'en_US', $written->sourceFingerprint ?? '');

        self::assertSame(ValueState::UpToDate, self::blockValue($state->values($entry, 'da_DK'))->state);
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidPaths
     */
    public function it_refuses_to_write_to_a_path_that_is_not_an_entry_field_or_a_block_field(string $path): void
    {
        $autosave = $this->prophesize(AutosaveInterface::class);
        $autosave->save(Argument::cetera())->shouldNotBeCalled();
        $state = new TranslationState($this->prophesize(WorkingCopyProviderInterface::class)->reveal(), $autosave->reveal(), self::translatableValues());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The path "%s" is not one of an entry field (entry.<field>) or a block field (block.<key>.<field>)', $path));

        $state->write(self::page('Hello'), 'da_DK', $path, 'Hej', 'en_US', Fingerprint::of('Hello'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidPaths(): iterable
    {
        yield 'a layout field' => ['layout.tagline'];
        yield 'an entry field with more after it' => ['entry.title.x'];
        yield 'an entry field with something before it' => ['the entry.title'];
        yield 'no entry field' => ['entry.'];
        yield 'a block without a field' => ['block.' . self::KEY];
        yield 'a block with an empty field' => ['block.' . self::KEY . '.'];
        yield 'a block field without a key' => ['block..text'];
        yield 'a block field with a key that is not a UUID' => ['block.Heading.text'];
        yield 'a block field with more after it' => ['block.' . self::KEY . '.text.x'];
        yield 'a block field with something before it' => ['a block.' . self::KEY . '.text'];
        yield 'a block field ending in a line break' => ['block.' . self::KEY . ".text\n"];
        yield 'an entry field ending in a line break' => ["entry.title\n"];
    }

    /**
     * @test
     */
    public function it_refuses_to_write_with_a_fingerprint_that_is_not_one(): void
    {
        $autosave = $this->prophesize(AutosaveInterface::class);
        $autosave->save(Argument::cetera())->shouldNotBeCalled();
        $state = new TranslationState($this->prophesize(WorkingCopyProviderInterface::class)->reveal(), $autosave->reveal(), self::translatableValues());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"ABC" is not a fingerprint: a lowercase hex SHA-256');

        $state->write(self::page('Hello'), 'da_DK', 'entry.title', 'Om os', 'en_US', 'ABC');
    }

    /**
     * @param iterable<TranslatableValue> $values
     */
    private static function blockValue(iterable $values): TranslatableValue
    {
        foreach ($values as $value) {
            if (str_starts_with($value->path, 'block.')) {
                return $value;
            }
        }

        self::fail('The entry has no block value');
    }

    /**
     * A never-published page in English, with a heading
     */
    private static function page(string $heading): Page
    {
        $page = new class() extends Page {
            private StoredTree $tree;

            public function getBlockTree(): StoredTree
            {
                return $this->tree;
            }

            public function setBlockTree(StoredTree $tree): void
            {
                $this->tree = $tree;
            }
        };
        $page->setCurrentLocale('en_US');
        $page->setFallbackLocale('en_US');
        $page->setTitle('About us');
        $page->setSlug('about-us');
        $page->setBlockTree(new StoredTree(null, [new StoredBlock(self::KEY, 'heading', 1, fields: ['text' => ['en_US' => $heading], 'level' => 'h2'])]));

        return $page;
    }

    /**
     * A never-published entry is its own working copy
     */
    private static function ownWorkingCopy(): WorkingCopyProviderInterface
    {
        return new class() implements WorkingCopyProviderInterface {
            public function getWorkingCopy(EntryInterface $entry): EntryInterface
            {
                return $entry;
            }

            public function getWorkingCopyWithDraft(EntryInterface $entry, ?DraftInterface $draft): EntryInterface
            {
                return $entry;
            }
        };
    }

    /**
     * Applies each save to the entry, as autosave applies it to a never-published entry's rows
     */
    private static function applyingAutosave(): AutosaveInterface
    {
        $applier = new ChangeSetApplier(self::blockTypes(), self::fieldHandlers(), self::contentTypes());

        return new class($applier) implements AutosaveInterface {
            private int $lockToken = 0;

            public function __construct(private readonly ChangeSetApplier $applier)
            {
            }

            public function load(EntryInterface $entry): LoadedWorkingCopy
            {
                return new LoadedWorkingCopy($entry, $this->lockToken);
            }

            public function save(EntryInterface $entry, ChangeSet $changeSet, int $lockToken): SaveResult
            {
                $this->applier->apply($changeSet, $entry);

                return new SaveResult(new LoadedWorkingCopy($entry, ++$this->lockToken), false);
            }

            public function discard(EntryInterface $entry): LoadedWorkingCopy
            {
                return $this->load($entry);
            }
        };
    }

    private static function translatableValues(): TranslatableValues
    {
        $fallbackChain = new class() implements FallbackChainInterface {
            public function localesFor(string $localeCode): array
            {
                return 'en_US' === $localeCode ? ['en_US'] : [$localeCode, 'en_US'];
            }
        };

        return new TranslatableValues(
            self::contentTypes(),
            self::blockTypes(),
            new LayoutRegistry([], new FieldReader()),
            new FallbackValues(self::fieldHandlers(), $fallbackChain),
            $fallbackChain,
        );
    }

    private static function contentTypes(): ContentTypeRegistry
    {
        return new ContentTypeRegistry(['page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page']]);
    }

    private static function blockTypes(): BlockTypeRegistry
    {
        return new BlockTypeRegistry(['heading' => Heading::class], new FieldReader());
    }

    private static function fieldHandlers(): FieldHandlerLocator
    {
        return new FieldHandlerLocator(new ServiceLocator([
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler(new IdentityTranslator()),
        ]));
    }
}
