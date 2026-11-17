<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Translation;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSet;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSetApplier;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\InvalidChangeSetException;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetEntryTranslated;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetEntryValue;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetLayoutTranslated;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetLayoutValue;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetTranslated;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\SetValue;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\LinkHandler;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Field\ToggleHandler;
use Setono\SyliusCMSPlugin\Localization\FallbackChain;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
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
use Setono\SyliusCMSPlugin\Tests\Support\Conditions;
use Setono\SyliusCMSPlugin\Tests\Support\Links;
use Setono\SyliusCMSPlugin\Tests\Unit\Translation\Fixture\LinkShapes;
use Setono\SyliusCMSPlugin\Tests\Unit\Translation\Fixture\Promo;
use Setono\SyliusCMSPlugin\Translation\Fingerprint;
use Setono\SyliusCMSPlugin\Translation\TranslatableValue;
use Setono\SyliusCMSPlugin\Translation\TranslatableValues;
use Setono\SyliusCMSPlugin\Translation\TranslationState;
use Setono\SyliusCMSPlugin\Translation\ValueState;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Sylius\Resource\Translation\Provider\ImmutableTranslationLocaleProvider;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;

final class TranslationStateTest extends TestCase
{
    use ProphecyTrait;

    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const LINK_KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

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
     * @test
     */
    public function it_writes_a_layout_value_and_where_it_was_translated_from_in_the_layout_the_working_copy_is_in(): void
    {
        $entry = self::page('Hello');
        $workingCopy = self::page('Hello', new StoredLayout('promo', 1, ['tagline' => ['en_US' => 'Big news']]));
        $fingerprint = Fingerprint::of('Big news');
        $autosave = $this->prophesize(AutosaveInterface::class);
        $autosave->load($entry)->willReturn(new LoadedWorkingCopy($workingCopy, 3));
        $autosave->save($entry, new ChangeSet([
            new SetLayoutValue('promo', 'tagline', 'da_DK', 'Nyhed'),
            new SetLayoutTranslated('promo', 'tagline', 'da_DK', ['from' => 'en_US', 'fingerprint' => $fingerprint]),
        ]), 3)->shouldBeCalledOnce()->willReturn(new SaveResult(new LoadedWorkingCopy($workingCopy, 4), false));

        $state = new TranslationState($this->prophesize(WorkingCopyProviderInterface::class)->reveal(), $autosave->reveal(), self::translatableValues());
        $state->write($entry, 'da_DK', 'layout.tagline', 'Nyhed', 'en_US', $fingerprint);
    }

    /**
     * @test
     */
    public function it_writes_a_layout_value_a_translator_read_so_it_is_up_to_date(): void
    {
        $entry = self::page('Hello', new StoredLayout('promo', 1, ['tagline' => ['en_US' => 'Big news']]));
        $state = new TranslationState($this->ownWorkingCopy(), $this->applyingAutosave(), self::translatableValues());
        $read = self::valueAt($state->values($entry, 'da_DK'), 'layout.tagline');
        self::assertSame(ValueState::Untranslated, $read->state);

        $state->write($entry, 'da_DK', 'layout.tagline', 'Nyhed', 'en_US', $read->sourceFingerprint ?? '');

        $written = self::valueAt($state->values($entry, 'da_DK'), 'layout.tagline');
        self::assertSame(ValueState::UpToDate, $written->state);
        self::assertSame('Nyhed', $written->value);
    }

    /**
     * @test
     */
    public function it_refuses_to_write_a_layout_value_of_an_entry_without_a_layout(): void
    {
        $entry = self::page('Hello');
        $autosave = $this->prophesize(AutosaveInterface::class);
        $autosave->load($entry)->willReturn(new LoadedWorkingCopy($entry, 0));
        $autosave->save(Argument::cetera())->shouldNotBeCalled();
        $state = new TranslationState($this->prophesize(WorkingCopyProviderInterface::class)->reveal(), $autosave->reveal(), self::translatableValues());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The path "layout.tagline" is a layout field, but the entry\'s draft has no layout');

        $state->write($entry, 'da_DK', 'layout.tagline', 'Nyhed', 'en_US', Fingerprint::of('Big news'));
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
        $state = new TranslationState($this->ownWorkingCopy(), $this->applyingAutosave(), self::translatableValues());
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
     * A Link field of the default shape is shared, but keeps its label, and the URL of a target whose ref is
     * translatable, per locale: a translator writes those, and the rest of the link stays as it is
     *
     * @test
     */
    public function it_writes_the_label_and_url_of_a_link_in_the_locale_into_the_link_and_where_they_were_translated_from(): void
    {
        $entry = self::page('Hello');
        $entry->getBlockTree()->blocks[] = new StoredBlock(self::LINK_KEY, 'link_shapes', 1, fields: [
            'link' => ['target' => 'url', 'ref' => ['en_US' => 'https://example.com/en'], 'anchor' => 'hours', 'label' => ['en_US' => 'Visit us']],
        ]);
        $state = new TranslationState($this->ownWorkingCopy(), $this->applyingAutosave(), self::translatableValues());
        $read = self::valueAt($state->values($entry, 'da_DK'), 'block.' . self::LINK_KEY . '.link');
        self::assertSame(ValueState::Untranslated, $read->state);
        self::assertSame(['label' => 'Visit us', 'ref' => 'https://example.com/en'], $read->sourceValue);

        $state->write($entry, 'da_DK', 'block.' . self::LINK_KEY . '.link', ['label' => 'Besøg os', 'ref' => 'https://example.com/da'], 'en_US', $read->sourceFingerprint ?? '');

        $written = self::valueAt($state->values($entry, 'da_DK'), 'block.' . self::LINK_KEY . '.link');
        self::assertSame(ValueState::UpToDate, $written->state);
        self::assertSame('en_US', $written->sourceLocaleCode);
        self::assertSame(['label' => 'Besøg os', 'ref' => 'https://example.com/da'], $written->value);
        self::assertSame(
            ['target' => 'url', 'ref' => ['en_US' => 'https://example.com/en', 'da_DK' => 'https://example.com/da'], 'anchor' => 'hours', 'label' => ['en_US' => 'Visit us', 'da_DK' => 'Besøg os']],
            $entry->getBlockTree()->findBlock(self::LINK_KEY)?->fields['link'],
        );
    }

    /**
     * @test
     */
    public function it_refuses_to_write_a_url_into_a_link_whose_ref_is_shared_by_every_locale(): void
    {
        $entry = self::page('Hello');
        $entry->getBlockTree()->blocks[] = new StoredBlock(self::LINK_KEY, 'link_shapes', 1, fields: [
            'link' => ['target' => 'app_store', 'ref' => 'CPH', 'anchor' => null, 'label' => ['en_US' => 'Visit us']],
        ]);
        $state = new TranslationState($this->ownWorkingCopy(), $this->applyingAutosave(), self::translatableValues());

        $this->expectException(InvalidChangeSetException::class);

        $state->write($entry, 'da_DK', 'block.' . self::LINK_KEY . '.link', ['label' => 'Besøg os', 'ref' => 'AAR'], 'en_US', Fingerprint::of(['label' => 'Visit us']));
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidPaths
     */
    public function it_refuses_to_write_to_a_path_that_is_not_an_entry_field_or_a_block_field(string $path): void
    {
        $entry = self::page('Hello', new StoredLayout('promo', 1));
        $autosave = $this->prophesize(AutosaveInterface::class);
        $autosave->load($entry)->willReturn(new LoadedWorkingCopy($entry, 0));
        $autosave->save(Argument::cetera())->shouldNotBeCalled();
        $state = new TranslationState($this->prophesize(WorkingCopyProviderInterface::class)->reveal(), $autosave->reveal(), self::translatableValues());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The path "%s" is not one of an entry field (entry.<field>), a layout field (layout.<field>) or a block field (block.<key>.<field>)', $path));

        $state->write($entry, 'da_DK', $path, 'Hej', 'en_US', Fingerprint::of('Hello'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidPaths(): iterable
    {
        yield 'no layout field' => ['layout.'];
        yield 'a layout field with more after it' => ['layout.tagline.x'];
        yield 'a layout field with something before it' => ['the layout.tagline'];
        yield 'a layout field ending in a line break' => ["layout.tagline\n"];
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
     * @param iterable<TranslatableValue> $values
     */
    private static function valueAt(iterable $values, string $path): TranslatableValue
    {
        foreach ($values as $value) {
            if ($path === $value->path) {
                return $value;
            }
        }

        self::fail(sprintf('The entry has no value at %s', $path));
    }

    /**
     * A never-published page in English, with a heading, in the layout when it's given
     */
    private static function page(string $heading, ?StoredLayout $layout = null): Page
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
        $page->setBlockTree(new StoredTree($layout, [new StoredBlock(self::KEY, 'heading', 1, fields: ['text' => ['en_US' => $heading], 'level' => 'h2'])]));

        return $page;
    }

    /**
     * A never-published entry is its own working copy
     */
    private function ownWorkingCopy(): WorkingCopyProviderInterface
    {
        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopy(Argument::type(EntryInterface::class))->willReturnArgument(0);

        return $workingCopies->reveal();
    }

    /**
     * Applies each save to the entry, as autosave applies it to a never-published entry's rows
     */
    private function applyingAutosave(): AutosaveInterface
    {
        $applier = new ChangeSetApplier(self::blockTypes(), self::fieldHandlers(), self::contentTypes(), Conditions::registry(), self::layouts());
        $lockToken = 0;

        $autosave = $this->prophesize(AutosaveInterface::class);
        $autosave->load(Argument::type(EntryInterface::class))->will(static function (array $arguments) use (&$lockToken): LoadedWorkingCopy {
            self::assertInstanceOf(EntryInterface::class, $arguments[0]);

            return new LoadedWorkingCopy($arguments[0], $lockToken);
        });
        $autosave->save(Argument::type(EntryInterface::class), Argument::type(ChangeSet::class), Argument::type('int'))->will(static function (array $arguments) use ($applier, &$lockToken): SaveResult {
            [$entry, $changeSet] = $arguments;
            self::assertInstanceOf(EntryInterface::class, $entry);
            self::assertInstanceOf(ChangeSet::class, $changeSet);
            $applier->apply($changeSet, $entry);

            return new SaveResult(new LoadedWorkingCopy($entry, ++$lockToken), false);
        });

        return $autosave->reveal();
    }

    private static function translatableValues(): TranslatableValues
    {
        $fallbackChain = new FallbackChain(new ImmutableTranslationLocaleProvider(['en_US'], 'en_US'));

        return new TranslatableValues(
            self::contentTypes(),
            self::blockTypes(),
            self::layouts(),
            new FallbackValues(self::fieldHandlers(), $fallbackChain),
            $fallbackChain,
        );
    }

    private static function contentTypes(): ContentTypeRegistry
    {
        return new ContentTypeRegistry(['page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page']]);
    }

    private static function layouts(): LayoutRegistry
    {
        return new LayoutRegistry(['page' => ['promo' => Promo::class]], new FieldReader(), ['page' => 'promo']);
    }

    private static function blockTypes(): BlockTypeRegistry
    {
        return new BlockTypeRegistry(['heading' => Heading::class, 'link_shapes' => LinkShapes::class], new FieldReader());
    }

    private static function fieldHandlers(): FieldHandlerLocator
    {
        return new FieldHandlerLocator(new ServiceLocator([
            LinkHandler::class => static fn (): LinkHandler => Links::handler(),
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler(new IdentityTranslator()),
            ToggleHandler::class => static fn (): ToggleHandler => new ToggleHandler(),
        ]));
    }
}
