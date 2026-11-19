<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Admin\ExportChoice;
use Setono\SyliusCMSPlugin\Admin\ExportChoices;
use Setono\SyliusCMSPlugin\Admin\ExportFolderChoice;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Localization\FallbackChain;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\EditorPreset;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\MediaFolder;
use Setono\SyliusCMSPlugin\Model\MediaItem;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Preset\EditorPresetContent;
use Setono\SyliusCMSPlugin\Preset\Preset;
use Setono\SyliusCMSPlugin\Preset\TranslationKey;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Repository\EditorPresetRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaFolderRepositoryInterface;
use Sylius\Resource\Translation\Provider\ImmutableTranslationLocaleProvider;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What the Transfer screen's export offers to pick and how it names it: entries by their title in the admin user's
 * locale and their content type, found by a search that leaves out disabled content types, editor presets by their name
 * and category, media items by their name and filename, and media folders under their parents. See "Transfer" in
 * docs/spec/admin.md
 */
final class ExportChoicesTest extends TestCase
{
    use ProphecyTrait;

    private const PAGE = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e';

    private const SNIPPET = '0193a5be-7255-7f00-8a1b-2c3d4e5f6a7b';

    private const UNTITLED = '0193a5be-7256-7a01-8b2c-3d4e5f6a7b8c';

    /** @var ObjectProphecy<EntryRepositoryInterface> */
    private ObjectProphecy $entries;

    /** @var ObjectProphecy<EditorPresetRepositoryInterface> */
    private ObjectProphecy $presets;

    /** @var ObjectProphecy<MediaFolderRepositoryInterface> */
    private ObjectProphecy $folders;

    protected function setUp(): void
    {
        $this->entries = $this->prophesize(EntryRepositoryInterface::class);
        $this->presets = $this->prophesize(EditorPresetRepositoryInterface::class);
        $this->folders = $this->prophesize(MediaFolderRepositoryInterface::class);
    }

    /**
     * @test
     */
    public function it_lists_the_entries_with_the_uuids_in_the_order_given_leaving_out_a_uuid_with_none(): void
    {
        $page = self::page(self::PAGE, ['en_US' => 'Autumn sale']);
        $snippet = self::snippet(self::SNIPPET, ['en_US' => 'Autumn banner']);
        $gone = '0193a5be-dead-7000-8000-000000000000';
        // The repository finds them in an order of its own
        $this->entries->findByUuids([self::SNIPPET, $gone, self::PAGE])->willReturn([$page, $snippet]);

        self::assertEquals([
            new ExportChoice(self::SNIPPET, 'Autumn banner', 'setono_sylius_cms.content_type.snippet'),
            new ExportChoice(self::PAGE, 'Autumn sale', 'setono_sylius_cms.content_type.page'),
        ], $this->choices()->entries([self::SNIPPET, $gone, self::PAGE], 'en_US'));
    }

    /**
     * @test
     */
    public function it_names_an_entry_by_its_title_in_the_locale_along_its_fallback_chain_and_otherwise_by_its_first_title_or_uuid(): void
    {
        $page = self::page(self::PAGE, ['en_US' => 'Autumn sale', 'da_DK' => 'Efterårsudsalg']);
        $snippet = self::snippet(self::SNIPPET, ['de_DE' => 'Herbstbanner']);
        $untitled = self::page(self::UNTITLED, []);

        $choices = $this->choices()->ofEntries([$page, $snippet, $untitled], 'da_DK');
        self::assertSame(['Efterårsudsalg', 'Herbstbanner', self::UNTITLED], array_map(static fn (ExportChoice $choice): string => $choice->title, $choices));

        // sv_SE has no title, so it falls back to the default locale, en_US
        self::assertSame('Autumn sale', $this->choices()->ofEntries([$page], 'sv_SE')[0]->title);
    }

    /**
     * @test
     */
    public function it_searches_the_entries_leaving_out_those_of_a_disabled_content_type(): void
    {
        $page = self::page(self::PAGE, ['en_US' => 'Autumn sale']);
        // At most 20 entries
        $this->entries->search('autumn', 20, ['snippet'])->willReturn([$page])->shouldBeCalledOnce();

        self::assertEquals([new ExportChoice(self::PAGE, 'Autumn sale', 'setono_sylius_cms.content_type.page')], $this->choices(disabled: ['snippet'])->search('autumn', 'en_US'));
    }

    /**
     * @test
     */
    public function it_finds_nothing_for_a_blank_search_without_asking_the_repository(): void
    {
        $this->entries->search(Argument::cetera())->shouldNotBeCalled();

        self::assertSame([], $this->choices()->search('', 'en_US'));
        self::assertSame([], $this->choices()->search("  \t", 'en_US'));
    }

    /**
     * @test
     */
    public function it_names_a_media_item_by_its_name_and_tells_it_apart_by_its_original_filename(): void
    {
        $banner = new MediaItem();
        $banner->setName('Autumn banner');
        $banner->setOriginalFilename('autumn-banner.jpg');
        $video = new MediaItem();
        $video->setKind(MediaKind::Video);
        $video->setName('Lookbook');
        $video->setOriginalFilename('lookbook.mp4');

        self::assertEquals([
            new ExportChoice($banner->getUuid(), 'Autumn banner', 'autumn-banner.jpg'),
            new ExportChoice($video->getUuid(), 'Lookbook', 'lookbook.mp4'),
        ], $this->choices()->ofMediaItems([$banner, $video]));
        self::assertSame([], $this->choices()->ofMediaItems([]));
    }

    /**
     * @test
     */
    public function it_lists_every_editor_preset_in_the_order_saved_with_its_name_and_category_translated(): void
    {
        $hero = self::preset(new Preset('Autumn hero', [], 'Campaigns'));
        $footer = self::preset(new Preset(new TranslationKey('app.preset.footer'), [], new TranslationKey('app.preset.layout', 'presets')));
        $plain = self::preset(new Preset('Plain', []));
        $this->presets->findAllInCreationOrder()->willReturn([$hero, $footer, $plain]);

        self::assertEquals([
            new ExportChoice($hero->getUuid(), 'Autumn hero', 'Campaigns'),
            new ExportChoice($footer->getUuid(), 'Footer (translated)', 'Layout (translated from presets)'),
            new ExportChoice($plain->getUuid(), 'Plain', null),
        ], $this->choices()->presets());
    }

    /**
     * @test
     */
    public function it_names_an_editor_preset_it_cannot_read_by_its_uuid(): void
    {
        $broken = new EditorPreset();
        $broken->setContent('{"not": "a preset"}');
        $other = self::preset(new Preset('Autumn hero', []));
        // The content of another preset
        $copied = new EditorPreset();
        $copied->setContent($other->getContent());
        $this->presets->findAllInCreationOrder()->willReturn([$broken, $copied]);

        self::assertEquals([
            new ExportChoice($broken->getUuid(), $broken->getUuid()),
            new ExportChoice($copied->getUuid(), $copied->getUuid()),
        ], $this->choices()->presets());
    }

    /**
     * @test
     */
    public function it_lists_every_media_folder_under_its_parent_in_name_order(): void
    {
        $archive = self::folder('Archive');
        $year = self::folder('2025', $archive);
        $campaigns = self::folder('Campaigns');
        $autumn = self::folder('Autumn', $campaigns);
        $banners = self::folder('Banners', $autumn);
        $spring = self::folder('Spring', $campaigns);
        // The repository lists them by name, whatever their parent
        $this->folders->findAllByName()->willReturn([$year, $archive, $autumn, $banners, $campaigns, $spring]);

        self::assertEquals([
            new ExportFolderChoice($archive->getUuid(), 'Archive', 0),
            new ExportFolderChoice($year->getUuid(), '2025', 1),
            new ExportFolderChoice($campaigns->getUuid(), 'Campaigns', 0),
            new ExportFolderChoice($autumn->getUuid(), 'Autumn', 1),
            new ExportFolderChoice($banners->getUuid(), 'Banners', 2),
            new ExportFolderChoice($spring->getUuid(), 'Spring', 1),
        ], $this->choices()->folders());
    }

    /**
     * @test
     */
    public function it_lists_no_media_folders_when_there_are_none(): void
    {
        $this->folders->findAllByName()->willReturn([]);

        self::assertSame([], $this->choices()->folders());
    }

    /**
     * @param list<string> $disabled the codes of the content types switched off
     */
    private function choices(array $disabled = []): ExportChoices
    {
        $translator = $this->prophesize(TranslatorInterface::class);
        // The keys of the preset in the list, translated by the translator double
        [$footer, $layout] = ['app.preset.footer', 'app.preset.layout'];
        $translator->trans($footer, [], 'messages', null)->willReturn('Footer (translated)');
        $translator->trans($layout, [], 'presets', null)->willReturn('Layout (translated from presets)');

        return new ExportChoices(
            $this->entries->reveal(),
            $this->presets->reveal(),
            $this->folders->reveal(),
            new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
                'snippet' => ['model' => Snippet::class, 'translation' => EntryTranslation::class, 'resource' => 'setono_sylius_cms.snippet'],
            ], disabled: $disabled),
            new FallbackValues(new FieldHandlerLocator(new ServiceLocator([])), new FallbackChain(new ImmutableTranslationLocaleProvider(['en_US', 'da_DK', 'de_DE', 'sv_SE'], 'en_US'))),
            $translator->reveal(),
        );
    }

    /**
     * @param array<string, string> $titles by locale
     */
    private static function page(string $uuid, array $titles): Page
    {
        return self::titled(new Page(), $uuid, $titles);
    }

    /**
     * @param array<string, string> $titles by locale
     */
    private static function snippet(string $uuid, array $titles): Snippet
    {
        return self::titled(new Snippet(), $uuid, $titles);
    }

    /**
     * @template T of EntryInterface
     *
     * @param T $entry
     * @param array<string, string> $titles by locale
     *
     * @return T
     */
    private static function titled(EntryInterface $entry, string $uuid, array $titles): EntryInterface
    {
        $entry->setUuid($uuid);
        foreach ($titles as $localeCode => $title) {
            $entry->setCurrentLocale($localeCode);
            $entry->setTitle($title);
        }

        return $entry;
    }

    private static function preset(Preset $preset): EditorPreset
    {
        $row = new EditorPreset();
        (new EditorPresetContent())->write($row, new Preset($preset->name, $preset->blocks, $preset->category, changedAt: new \DateTimeImmutable('2026-11-10T08:00:00Z')));

        return $row;
    }

    private static function folder(string $name, ?MediaFolder $parent = null): MediaFolder
    {
        $folder = new MediaFolder();
        $folder->setName($name);
        $folder->setParent($parent);

        return $folder;
    }
}
