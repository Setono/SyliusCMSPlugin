<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Admin\EntryRow;
use Setono\SyliusCMSPlugin\Admin\EntryRows;
use Setono\SyliusCMSPlugin\Admin\StatusLabel;
use Setono\SyliusCMSPlugin\Admin\StatusLabeler;
use Setono\SyliusCMSPlugin\Builder\EntryStatus;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\Draft;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EntryRowsTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<DraftRepositoryInterface> */
    private ObjectProphecy $drafts;

    /** @var ObjectProphecy<UrlGeneratorInterface> */
    private ObjectProphecy $urlGenerator;

    protected function setUp(): void
    {
        $this->drafts = $this->prophesize(DraftRepositoryInterface::class);
        $this->drafts->findByEntries(Argument::any())->willReturn([]);

        $this->urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $this->urlGenerator->generate('setono_sylius_cms_shop_entry', Argument::type('array'))->will(static function (array $arguments): string {
            /** @var array{_locale: string, path: string} $parameters */
            $parameters = $arguments[1];

            return sprintf('/%s/%s', $parameters['_locale'], $parameters['path']);
        });
    }

    /**
     * @test
     */
    public function it_shows_an_entry_that_has_no_draft_with_its_title_and_url_from_its_rows(): void
    {
        $page = self::page(['en_US' => ['About us', 'about-us']]);

        [$row] = $this->rows([$page]);

        self::assertSame($page, $row->entry);
        self::assertSame('page', $row->contentType->code);
        self::assertSame('About us', $row->title);
        self::assertSame('/en_US/about-us', $row->url);
        self::assertEquals([new StatusLabel(StatusLabel::NEVER_PUBLISHED)], $row->statusLabels);
        self::assertSame(EntryStatus::NEVER_PUBLISHED, $row->status);
    }

    /**
     * @test
     */
    public function it_reads_the_title_and_url_of_an_entry_with_a_draft_from_the_drafts_json(): void
    {
        $page = self::page(['en_US' => ['About us', 'about-us']]);
        $page->publish(new \DateTimeImmutable('2026-11-01T00:00:00Z'));
        $other = self::page(['en_US' => ['Contact', 'contact']]);
        $other->publish(new \DateTimeImmutable('2026-11-01T00:00:00Z'));
        $draft = self::draft($page, ['en_US' => ['About our shop', 'about-our-shop']]);
        $this->drafts->findByEntries([$page, $other])->willReturn([$draft]);

        [$row, $otherRow] = $this->rows([$page, $other]);

        // The row keeps the draft, for what else reads the working copy, such as its translation chips
        self::assertSame($draft, $row->draft);
        self::assertNull($otherRow->draft);
        self::assertSame('About our shop', $row->title);
        self::assertSame('/en_US/about-our-shop', $row->url);
        self::assertEquals([new StatusLabel(StatusLabel::PUBLISHED), new StatusLabel(StatusLabel::UNPUBLISHED_CHANGES)], $row->statusLabels);
        self::assertSame(EntryStatus::UNPUBLISHED_CHANGES, $row->status);

        self::assertSame('Contact', $otherRow->title);
        self::assertEquals([new StatusLabel(StatusLabel::PUBLISHED)], $otherRow->statusLabels);
        self::assertSame(EntryStatus::PUBLISHED, $otherRow->status);
    }

    /**
     * @test
     */
    public function it_shows_the_admin_users_locale_or_else_the_stores_default_locale_or_else_the_first(): void
    {
        $both = self::page(['en_US' => ['About us', 'about-us'], 'da_DK' => ['Om os', 'om-os']]);
        $english = self::page(['en_US' => ['Contact', 'contact']]);
        $german = self::page(['de_DE' => ['Impressum', 'impressum'], 'fr_FR' => ['Mentions légales', 'mentions']]);

        $rows = $this->rows([$both, $english, $german], 'da_DK');

        self::assertSame(['Om os', 'Contact', 'Impressum'], array_map(static fn (EntryRow $row): ?string => $row->title, $rows));
        self::assertSame(['/da_DK/om-os', '/en_US/contact', '/de_DE/impressum'], array_map(static fn (EntryRow $row): ?string => $row->url, $rows));
    }

    /**
     * @test
     */
    public function it_shows_the_admin_users_locale_from_a_draft_too(): void
    {
        $page = self::page(['en_US' => ['About us', 'about-us']]);
        $page->publish(new \DateTimeImmutable('2026-11-01T00:00:00Z'));
        $this->drafts->findByEntries([$page])->willReturn([self::draft($page, ['en_US' => ['About us', 'about-us'], 'da_DK' => ['Om os', 'om-os']])]);

        [$row] = $this->rows([$page], 'da_DK');

        self::assertSame('Om os', $row->title);
        self::assertSame('/da_DK/om-os', $row->url);

        [$row] = $this->rows([$page], 'en_US');

        self::assertSame('About us', $row->title);
        self::assertSame('/en_US/about-us', $row->url);
    }

    /**
     * @test
     */
    public function it_reads_the_translations_of_a_draft_it_can_read_beside_one_it_cannot(): void
    {
        $page = self::page(['en_US' => ['About us', 'about-us']]);
        $page->publish(new \DateTimeImmutable('2026-11-01T00:00:00Z'));
        $draft = new Draft();
        $draft->setEntry($page);
        $draft->setContent(CanonicalJson::encode(['translations' => [
            'da_DK' => 'Om os',
            'en_US' => ['fields' => ['slug' => 'about-our-shop', 'title' => 'About our shop']],
        ]]));
        $this->drafts->findByEntries([$page])->willReturn([$draft]);

        [$row] = $this->rows([$page], 'da_DK');

        self::assertSame('About our shop', $row->title);
        self::assertSame('/en_US/about-our-shop', $row->url);
    }

    /**
     * @test
     */
    public function it_has_no_url_for_a_content_type_without_its_own_url_or_an_entry_without_a_slug(): void
    {
        $recipe = new Recipe();
        $recipe->setCurrentLocale('en_US');
        $recipe->setTitle('Apple pie');
        $withoutSlug = self::page(['en_US' => ['About us', null]]);

        $rows = $this->rows([$recipe, $withoutSlug]);

        self::assertSame('recipe', $rows[0]->contentType->code);
        self::assertSame('Apple pie', $rows[0]->title);
        self::assertNull($rows[0]->url);
        self::assertSame('About us', $rows[1]->title);
        self::assertNull($rows[1]->url);
    }

    /**
     * @test
     */
    public function it_has_neither_a_title_nor_a_url_for_an_entry_without_translations(): void
    {
        [$row] = $this->rows([new Page()]);

        self::assertNull($row->title);
        self::assertNull($row->url);
    }

    /**
     * @test
     *
     * @dataProvider provideDraftsItCannotRead
     */
    public function it_shows_nothing_of_a_draft_whose_translations_it_cannot_read(mixed $content): void
    {
        $page = self::page(['en_US' => ['About us', 'about-us']]);
        $page->publish(new \DateTimeImmutable('2026-11-01T00:00:00Z'));
        $draft = new Draft();
        $draft->setEntry($page);
        $draft->setContent(CanonicalJson::encode($content));
        $this->drafts->findByEntries([$page])->willReturn([$draft]);

        [$row] = $this->rows([$page]);

        self::assertNull($row->title);
        self::assertNull($row->url);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideDraftsItCannotRead(): iterable
    {
        yield 'not an object' => [7];
        yield 'no translations' => [['uuid' => '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e']];
        yield 'translations that are a list' => [['translations' => [['fields' => ['slug' => 'about-us', 'title' => 'About us']]]]];
        yield 'a translation that is not an object' => [['translations' => ['en_US' => 'About us']]];
        yield 'a translation without fields' => [['translations' => ['en_US' => ['shown' => true]]]];
        yield 'fields that are not an object' => [['translations' => ['en_US' => ['fields' => 'About us']]]];
        yield 'no title or slug' => [['translations' => ['en_US' => ['fields' => []]]]];
        yield 'a title and a slug that are not text' => [['translations' => ['en_US' => ['fields' => ['slug' => ['about-us'], 'title' => 7]]]]];
    }

    /**
     * @test
     */
    public function it_loads_nothing_for_a_page_without_entries(): void
    {
        $this->drafts->findByEntries(Argument::any())->shouldNotBeCalled();

        $this->entryRows()->load(self::pageOf([]));
    }

    /**
     * @test
     */
    public function it_loads_the_drafts_of_a_page_of_entries_in_one_query_and_keeps_their_rows(): void
    {
        $about = self::page(['en_US' => ['About us', 'about-us']]);
        $contact = self::page(['en_US' => ['Contact', 'contact']]);
        $this->drafts->findByEntries([$about, $contact])->willReturn([])->shouldBeCalledOnce();
        $rows = $this->entryRows();

        $rows->load(self::pageOf([$about, $contact]));

        self::assertSame('About us', $rows->get($about)->title);
        self::assertSame('Contact', $rows->get($contact)->title);
        self::assertSame($rows->get($about), $rows->get($about));
    }

    /**
     * @test
     */
    public function it_loads_the_row_of_an_entry_that_was_not_on_a_page_alone(): void
    {
        $about = self::page(['en_US' => ['About us', 'about-us']]);
        $contact = self::page(['en_US' => ['Contact', 'contact']]);
        $this->drafts->findByEntries([$about])->willReturn([])->shouldBeCalledOnce();
        $this->drafts->findByEntries([$contact])->willReturn([])->shouldBeCalledOnce();
        $rows = $this->entryRows();

        $rows->load(self::pageOf([$about]));

        self::assertSame('Contact', $rows->get($contact)->title);
        self::assertSame('Contact', $rows->get($contact)->title);
    }

    /**
     * @test
     */
    public function it_forgets_the_rows_it_loaded_when_it_is_reset(): void
    {
        $about = self::page(['en_US' => ['About us', 'about-us']]);
        $this->drafts->findByEntries([$about])->willReturn([])->shouldBeCalledTimes(2);
        $rows = $this->entryRows();
        $rows->load(self::pageOf([$about]));
        $before = $rows->get($about);

        $rows->reset();

        self::assertNotSame($before, $rows->get($about));
    }

    /**
     * @param list<EntryInterface> $entries
     *
     * @return list<EntryRow>
     */
    private function rows(array $entries, string $localeCode = 'en_US'): array
    {
        $rows = $this->entryRows($localeCode);
        $rows->load(self::pageOf($entries));

        return array_map($rows->get(...), $entries);
    }

    /**
     * @param string $localeCode the admin user's locale
     */
    private function entryRows(string $localeCode = 'en_US'): EntryRows
    {
        $translationLocaleProvider = $this->prophesize(TranslationLocaleProviderInterface::class);
        $translationLocaleProvider->getDefaultLocaleCode()->willReturn('en_US');
        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn($localeCode);

        return new EntryRows(
            $this->drafts->reveal(),
            new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
                'recipe' => ['model' => Recipe::class, 'translation' => EntryTranslation::class, 'resource' => 'app.recipe'],
            ]),
            new StatusLabeler(new MockClock('2026-11-16 12:00:00')),
            $this->urlGenerator->reveal(),
            $translationLocaleProvider->reveal(),
            $localeContext->reveal(),
        );
    }

    /**
     * A page of entries as Pagerfanta gives one: an iterable, whose keys don't matter
     *
     * @param list<EntryInterface> $entries
     *
     * @return iterable<EntryInterface>
     */
    private static function pageOf(array $entries): iterable
    {
        return (static function () use ($entries): \Generator {
            foreach ($entries as $entry) {
                yield $entry->getUuid() => $entry;
            }
        })();
    }

    /**
     * @param array<string, array{string, string|null}> $translations locale => [title, slug]
     */
    private static function page(array $translations): Page
    {
        $page = new Page();
        foreach ($translations as $localeCode => [$title, $slug]) {
            $page->setCurrentLocale($localeCode);
            $page->setTitle($title);
            $page->setSlug($slug);
        }

        return $page;
    }

    /**
     * A draft in the entry format, with these translations
     *
     * @param array<string, array{string, string|null}> $translations locale => [title, slug]
     */
    private static function draft(Page $page, array $translations): DraftInterface
    {
        $draft = new Draft();
        $draft->setEntry($page);
        $draft->setContent(CanonicalJson::encode([
            'uuid' => $page->getUuid(),
            'contentType' => 'page',
            'translations' => array_map(static fn (array $translation): array => [
                'shown' => true,
                'fields' => ['slug' => $translation[1], 'title' => $translation[0]],
                'translated' => new \stdClass(),
            ], $translations),
        ]));

        return $draft;
    }
}
