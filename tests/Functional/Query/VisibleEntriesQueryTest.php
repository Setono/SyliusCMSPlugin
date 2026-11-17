<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Query;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Query\NotVisibleReason;
use Setono\SyliusCMSPlugin\Query\VisibleEntriesQueryInterface;
use Setono\SyliusCMSPlugin\Registry\UnknownContentTypeException;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The entries query every listing, source and URL builds on. It keeps only visible entries, in SQL.
 * See "Visible queries" in docs/spec/sources.md and "When an entry shows" in docs/spec/content-model.md
 */
final class VisibleEntriesQueryTest extends KernelTestCase
{
    use Entries;

    private const NOW = '2026-11-27 12:00:00';

    /**
     * @test
     */
    public function it_keeps_a_published_enabled_entry_in_the_channel_translated_into_the_locale_for_everyone(): void
    {
        self::createPage('Visible', 'visible', published: true);

        self::assertSame(['Visible'], self::visibleTitles(self::context()));
    }

    /**
     * @test
     */
    public function it_leaves_out_an_entry_that_was_never_published(): void
    {
        self::createPage('Visible', 'visible', published: true);
        self::createPage('Never published', 'never-published');

        self::assertSame(['Visible'], self::visibleTitles(self::context()));
    }

    /**
     * @test
     */
    public function it_leaves_out_a_disabled_entry(): void
    {
        self::createPage('Visible', 'visible', published: true);
        self::save(self::createPage('Disabled', 'disabled', published: true), static fn (PageInterface $page) => $page->setEnabled(false));

        self::assertSame(['Visible'], self::visibleTitles(self::context()));
    }

    /**
     * @test
     */
    public function it_keeps_an_entry_from_show_from_and_until_show_until(): void
    {
        self::save(self::createPage('From now', 'from-now', published: true), static fn (PageInterface $page) => $page->setDateWindow(new DateWindow(self::utc(self::NOW))));
        self::save(self::createPage('From later', 'from-later', published: true), static fn (PageInterface $page) => $page->setDateWindow(new DateWindow(self::utc('2026-11-27 12:00:01'))));
        self::save(self::createPage('Until later', 'until-later', published: true), static fn (PageInterface $page) => $page->setDateWindow(new DateWindow(null, self::utc('2026-11-27 12:00:01'))));
        self::save(self::createPage('Until now', 'until-now', published: true), static fn (PageInterface $page) => $page->setDateWindow(new DateWindow(null, self::utc(self::NOW))));
        self::save(self::createPage('Around now', 'around-now', published: true), static fn (PageInterface $page) => $page->setDateWindow(new DateWindow(self::utc('2026-11-01 00:00:00'), self::utc('2026-12-01 00:00:00'))));
        self::save(self::createPage('Ended', 'ended', published: true), static fn (PageInterface $page) => $page->setDateWindow(new DateWindow(self::utc('2026-11-01 00:00:00'), self::utc('2026-11-02 00:00:00'))));

        self::assertSame(['Around now', 'From now', 'Until later'], self::visibleTitles(self::context()));
    }

    /**
     * The render context's time can be in any time zone, such as the preview's forced Date
     *
     * @test
     */
    public function it_compares_the_date_window_with_the_time_as_an_instant(): void
    {
        self::save(self::createPage('From now', 'from-now', published: true), static fn (PageInterface $page) => $page->setDateWindow(new DateWindow(self::utc(self::NOW))));

        self::assertSame([], self::visibleTitles(self::context(now: new \DateTimeImmutable('2026-11-27 12:59:59', new \DateTimeZone('Europe/Copenhagen')))));
        self::assertSame(['From now'], self::visibleTitles(self::context(now: new \DateTimeImmutable('2026-11-27 13:00:00', new \DateTimeZone('Europe/Copenhagen')))));
    }

    /**
     * @test
     */
    public function it_leaves_out_an_entry_that_is_not_in_the_channel(): void
    {
        self::createPage('Visible', 'visible', published: true, channels: ['WEB', 'OTHER']);
        self::createPage('Elsewhere', 'elsewhere', published: true, channels: ['OTHER']);
        self::createPage('Nowhere', 'nowhere', published: true, channels: []);

        self::assertSame(['Visible'], self::visibleTitles(self::context()));
    }

    /**
     * @test
     */
    public function it_leaves_out_an_entry_without_a_translation_in_the_locale(): void
    {
        self::createPage('English only', 'english-only', published: true);
        $both = self::createPage('English and Danish', 'english-and-danish', published: true);
        self::addTranslation($both, 'da_DK', 'Engelsk og dansk');

        self::assertSame(['English and Danish', 'English only'], self::visibleTitles(self::context()));
        self::assertSame(['English and Danish'], self::visibleTitles(self::context(localeCode: 'da_DK')));
    }

    /**
     * An entry has a translation in a locale once that translation has a title. See "Which locales an entry has" in
     * docs/spec/localization.md
     *
     * @test
     *
     * @dataProvider provideTitlesThatAreNone
     */
    public function it_leaves_out_an_entry_whose_translation_in_the_locale_has_no_title(?string $title): void
    {
        $page = self::createPage('English and Danish', 'english-and-danish', published: true);
        self::addTranslation($page, 'da_DK', $title, 'engelsk-og-dansk');

        self::assertSame([], self::visibleTitles(self::context(localeCode: 'da_DK')));
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function provideTitlesThatAreNone(): iterable
    {
        yield 'no title' => [null];
        yield 'an empty title' => [''];
    }

    /**
     * A held-back translation counts as missing, and the entry never falls back to another locale.
     * See "Held-back translations" in docs/spec/publishing.md
     *
     * @test
     */
    public function it_leaves_out_an_entry_whose_translation_in_the_locale_is_held_back(): void
    {
        $page = self::createPage('English and Danish', 'english-and-danish', published: true);
        self::addTranslation($page, 'da_DK', 'Engelsk og dansk', shown: false);

        self::assertSame([], self::visibleTitles(self::context(localeCode: 'da_DK')));
        self::assertSame(['English and Danish'], self::visibleTitles(self::context()));
    }

    /**
     * @test
     */
    public function it_keeps_only_the_entries_whose_audience_allows_the_shopper(): void
    {
        self::createPage('Everyone', 'everyone', published: true);
        self::save(self::createPage('Only wholesale', 'only-wholesale', published: true), static fn (PageInterface $page) => self::channelsAndAudience()->setAudience($page, Audience::only('wholesale')));
        self::save(self::createPage('Only guests', 'only-guests', published: true), static fn (PageInterface $page) => self::channelsAndAudience()->setAudience($page, Audience::only('@guest')));
        self::save(self::createPage('Only without a group', 'only-without-a-group', published: true), static fn (PageInterface $page) => self::channelsAndAudience()->setAudience($page, Audience::only('@no_group')));
        self::save(self::createPage('Except wholesale and guests', 'except-wholesale-and-guests', published: true), static fn (PageInterface $page) => self::channelsAndAudience()->setAudience($page, Audience::except('wholesale', '@guest')));
        self::save(self::createPage('Only nobody', 'only-nobody', published: true), static fn (PageInterface $page) => self::channelsAndAudience()->setAudience($page, Audience::only()));

        self::assertSame(['Everyone', 'Only guests'], self::visibleTitles(self::context(customerGroup: '@guest')));
        self::assertSame(['Everyone', 'Except wholesale and guests', 'Only without a group'], self::visibleTitles(self::context(customerGroup: '@no_group')));
        self::assertSame(['Everyone', 'Only wholesale'], self::visibleTitles(self::context(customerGroup: 'wholesale')));
        self::assertSame(['Everyone', 'Except wholesale and guests'], self::visibleTitles(self::context(customerGroup: 'retail')));
    }

    /**
     * See "Deleted customer groups" in docs/spec/targeting.md
     *
     * @test
     */
    public function it_matches_nobody_with_a_deleted_customer_groups_code_in_an_audience(): void
    {
        self::save(self::createPage('Only a deleted group', 'only-deleted', published: true), static fn (PageInterface $page) => self::channelsAndAudience()->setAudience($page, Audience::only('deleted_group')));
        self::save(self::createPage('Except a deleted group', 'except-deleted', published: true), static fn (PageInterface $page) => self::channelsAndAudience()->setAudience($page, Audience::except('deleted_group')));

        foreach (['@guest', '@no_group', 'wholesale'] as $customerGroup) {
            self::assertSame(['Except a deleted group'], self::visibleTitles(self::context(customerGroup: $customerGroup)), $customerGroup);
        }
    }

    /**
     * @test
     */
    public function it_returns_a_query_builder_rooted_at_entry_without_an_order_that_callers_build_on(): void
    {
        $visible = self::createPage('Visible', 'visible', published: true);
        self::createPage('Also visible', 'also-visible', published: true);

        $queryBuilder = self::query()->createQueryBuilder('page', self::context());

        self::assertSame(['entry'], $queryBuilder->getRootAliases());
        self::assertSame([], $queryBuilder->getDQLPart('orderBy'));

        /** @var list<PageInterface> $found */
        $found = $queryBuilder->andWhere('entry.uuid = :uuid')->setParameter('uuid', $visible->getUuid())->getQuery()->getResult();

        self::assertSame([$visible->getUuid()], array_map(static fn (PageInterface $page): string => $page->getUuid(), $found));
    }

    /**
     * @test
     */
    public function it_needs_a_known_content_type(): void
    {
        $this->expectException(UnknownContentTypeException::class);

        self::query()->createQueryBuilder('recipe', self::context());
    }

    /**
     * The first rule of "When an entry shows" each entry fails, in its order, from the same rules the query filters on
     *
     * @test
     */
    public function it_says_why_each_entry_that_is_not_visible_is_not_by_the_first_rule_it_fails(): void
    {
        $visible = self::createPage('Visible', 'visible', published: true);
        self::addTranslation($visible, 'da_DK', 'Synlig');
        $draft = self::createPage('Draft', 'draft');
        self::save($draft, static fn (PageInterface $page) => $page->setEnabled(false));
        $disabled = self::createPage('Disabled', 'disabled', published: true);
        self::save($disabled, static fn (PageInterface $page) => $page->setEnabled(false));
        $ended = self::createPage('Ended', 'ended', published: true);
        self::save($ended, static fn (PageInterface $page) => $page->setDateWindow(new DateWindow(null, self::utc('2026-11-01 00:00:00'))));
        $elsewhere = self::createPage('Elsewhere', 'elsewhere', published: true, channels: ['OTHER']);
        $englishOnly = self::createPage('English only', 'english-only', published: true);
        $heldBack = self::createPage('Held back', 'held-back', published: true);
        self::addTranslation($heldBack, 'da_DK', 'Holdt tilbage', shown: false);
        $wholesale = self::createPage('Only wholesale', 'only-wholesale', published: true);
        self::addTranslation($wholesale, 'da_DK', 'Kun engros');
        self::save($wholesale, static fn (PageInterface $page) => self::channelsAndAudience()->setAudience($page, Audience::only('wholesale')));

        $reasons = self::query()->whyNotVisible('page', [
            $visible->getUuid(), $draft->getUuid(), $disabled->getUuid(), $ended->getUuid(), $elsewhere->getUuid(),
            $englishOnly->getUuid(), $heldBack->getUuid(), $wholesale->getUuid(), '0193a5be-0000-7000-8000-00000000dead',
        ], self::context(localeCode: 'da_DK'));
        ksort($reasons);

        $expected = [
            $draft->getUuid() => NotVisibleReason::NotPublished,
            $disabled->getUuid() => NotVisibleReason::Disabled,
            $ended->getUuid() => NotVisibleReason::OutsideDateWindow,
            $elsewhere->getUuid() => NotVisibleReason::NotInChannel,
            $englishOnly->getUuid() => NotVisibleReason::NotTranslated,
            $heldBack->getUuid() => NotVisibleReason::NotTranslated,
            $wholesale->getUuid() => NotVisibleReason::OutsideAudience,
        ];
        ksort($expected);

        self::assertSame($expected, $reasons);
    }

    /**
     * @test
     */
    public function it_says_why_only_of_the_entries_of_the_content_type(): void
    {
        $snippet = self::createSnippet('Unpublished snippet');

        self::assertSame([], self::query()->whyNotVisible('page', [$snippet->getUuid()], self::context()));
        self::assertSame([$snippet->getUuid() => NotVisibleReason::NotPublished], self::query()->whyNotVisible('snippet', [$snippet->getUuid()], self::context()));
        self::assertSame([], self::query()->whyNotVisible('snippet', [], self::context()));
    }

    /**
     * @return list<string> the titles of the visible pages in en_US, sorted
     */
    private static function visibleTitles(RenderContext $context): array
    {
        /** @var list<PageInterface> $pages */
        $pages = self::query()->createQueryBuilder('page', $context)->getQuery()->getResult();

        $titles = array_map(static fn (PageInterface $page): string => (string) $page->getTranslation('en_US')->getTitle(), $pages);
        sort($titles);

        return $titles;
    }

    private static function context(string $localeCode = 'en_US', string $customerGroup = '@guest', ?\DateTimeImmutable $now = null): RenderContext
    {
        /** @var ChannelRepositoryInterface<ChannelInterface> $channels */
        $channels = self::getContainer()->get('sylius.repository.channel');
        $channel = $channels->findOneByCode('WEB');
        self::assertInstanceOf(ChannelInterface::class, $channel);

        return new RenderContext($channel, $localeCode, 'USD', null, $customerGroup, $now ?? self::utc(self::NOW));
    }

    private static function query(): VisibleEntriesQueryInterface
    {
        /** @var VisibleEntriesQueryInterface $query */
        $query = self::getContainer()->get(VisibleEntriesQueryInterface::class);

        return $query;
    }

    /**
     * @param callable(PageInterface): void $change
     */
    private static function save(PageInterface $page, callable $change): void
    {
        $change($page);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();
        $manager->clear();
    }

    private static function utc(string $utc): \DateTimeImmutable
    {
        return new \DateTimeImmutable($utc, new \DateTimeZone('UTC'));
    }
}
