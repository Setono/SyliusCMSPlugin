<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Query\UrlClaimsQueryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Routing\UrlClaim;
use Setono\SyliusCMSPlugin\Routing\UrlClash;
use Setono\SyliusCMSPlugin\Routing\UrlClashFinder;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\Article;

final class UrlClashFinderTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<UrlClaimsQueryInterface> */
    private ObjectProphecy $claims;

    /** @var list<UrlClaim> the other entries' published URLs */
    private array $published = [];

    /** @var list<UrlClaim> the other entries' drafted slugs */
    private array $drafted = [];

    private Page $page;

    protected function setUp(): void
    {
        $this->claims = $this->prophesize(UrlClaimsQueryInterface::class);
        // Prophecy binds a promise's closure to the double, so it reaches the claims by reference
        $published = &$this->published;
        $drafted = &$this->drafted;
        $this->claims->published(Argument::cetera())->will(static function (array $arguments) use (&$published): array {
            /** @var array{string, array<string, string>, string} $arguments */
            return self::matching($published, ...$arguments);
        });
        $this->claims->drafted(Argument::cetera())->will(static function (array $arguments) use (&$drafted): array {
            /** @var array{string, array<string, string>, string} $arguments */
            return self::matching($drafted, ...$arguments);
        });
        $this->page = new Page();
    }

    /**
     * @test
     */
    public function it_finds_another_entrys_published_url_in_a_channel_and_locale_they_share(): void
    {
        $this->published[] = $other = self::claim('page', 'en_US', 'about-us', ['B2B', 'WEB']);

        $clashes = $this->finder()->findPublished($this->page, ['en_US' => 'about-us'], ['MOBILE', 'WEB']);

        self::assertEquals([new UrlClash('en_US', 'about-us', $other, ['WEB'])], $clashes);
    }

    /**
     * @test
     */
    public function it_lets_entries_with_no_channel_in_common_share_a_url(): void
    {
        $this->published[] = self::claim('page', 'en_US', 'about-us', ['B2B']);

        self::assertSame([], $this->finder()->findPublished($this->page, ['en_US' => 'about-us'], ['MOBILE', 'WEB']));
    }

    /**
     * @test
     */
    public function it_finds_no_clash_for_an_entry_in_no_channel_and_asks_nothing(): void
    {
        $this->claims->published(Argument::cetera())->shouldNotBeCalled();

        self::assertSame([], $this->finder()->findPublished($this->page, ['en_US' => 'about-us'], []));
    }

    /**
     * @test
     */
    public function it_keeps_a_url_unique_per_locale(): void
    {
        $this->published[] = self::claim('page', 'da_DK', 'about-us', ['WEB']);
        $this->published[] = $english = self::claim('page', 'en_US', 'om-os', ['WEB']);

        self::assertEquals(
            [new UrlClash('en_US', 'om-os', $english, ['WEB'])],
            $this->finder()->findPublished($this->page, ['da_DK' => 'om-os', 'en_US' => 'om-os'], ['WEB']),
        );
    }

    /**
     * The URL is the prefix plus the slug, so a page's slug blog/hello clashes with the article hello under the
     * prefix "articles", and the article's slug hello doesn't clash with the page hello
     *
     * @test
     */
    public function it_takes_the_url_as_the_prefix_plus_the_slug_across_content_types(): void
    {
        $this->published[] = $article = self::claim('article', 'en_US', 'hello', ['WEB']);
        $this->published[] = $page = self::claim('page', 'en_US', 'hello', ['WEB']);

        self::assertEquals(
            [new UrlClash('en_US', 'articles/hello', $article, ['WEB'])],
            $this->finder()->findPublished($this->page, ['en_US' => 'articles/hello'], ['WEB']),
        );
        self::assertEquals(
            [new UrlClash('en_US', 'articles/hello', $article, ['WEB'])],
            $this->finder()->findPublished(new Article(), ['en_US' => 'hello'], ['WEB']),
        );
        self::assertEquals(
            [new UrlClash('en_US', 'hello', $page, ['WEB'])],
            $this->finder()->findPublished($this->page, ['en_US' => 'hello'], ['WEB']),
        );
    }

    /**
     * @test
     */
    public function it_checks_each_slug_with_a_url(): void
    {
        $this->published[] = $other = self::claim('page', 'en_US', 'about-us', ['WEB']);

        self::assertEquals(
            [new UrlClash('en_US', 'about-us', $other, ['WEB'])],
            $this->finder()->findPublished($this->page, ['da_DK' => '', 'en_US' => 'about-us'], ['WEB']),
        );
    }

    /**
     * @test
     */
    public function it_finds_all_the_entries_that_have_a_url(): void
    {
        $this->published[] = $web = self::claim('page', 'en_US', 'about-us', ['WEB']);
        $this->published[] = $mobile = self::claim('page', 'en_US', 'about-us', ['MOBILE']);

        self::assertEquals(
            [new UrlClash('en_US', 'about-us', $web, ['WEB']), new UrlClash('en_US', 'about-us', $mobile, ['MOBILE'])],
            $this->finder()->findPublished($this->page, ['en_US' => 'about-us'], ['MOBILE', 'WEB']),
        );
    }

    /**
     * @test
     */
    public function it_leaves_the_entry_itself_out(): void
    {
        $this->published[] = self::claim('page', 'en_US', 'about-us', ['WEB'], $this->page->getUuid());

        self::assertSame([], $this->finder()->findPublished($this->page, ['en_US' => 'about-us'], ['WEB']));
    }

    /**
     * @test
     */
    public function it_finds_no_clash_for_an_entry_without_a_url_of_its_own(): void
    {
        $this->published[] = self::claim('page', 'en_US', 'pancakes', ['WEB']);
        $this->claims->published(Argument::cetera())->shouldNotBeCalled();

        self::assertSame([], $this->finder()->findPublished(new Recipe(), ['en_US' => 'pancakes'], ['WEB']));
    }

    /**
     * @test
     */
    public function it_finds_other_entries_drafted_slugs_apart_from_their_published_urls(): void
    {
        $this->published[] = $published = self::claim('page', 'en_US', 'about-us', ['WEB']);
        $this->drafted[] = $drafted = self::claim('page', 'en_US', 'contact', ['WEB']);

        $slugs = ['en_US' => 'about-us', 'da_DK' => 'contact'];
        self::assertEquals([new UrlClash('en_US', 'about-us', $published, ['WEB'])], $this->finder()->findPublished($this->page, $slugs, ['WEB']));
        self::assertSame([], $this->finder()->findDrafted($this->page, $slugs, ['WEB']));
        self::assertEquals([new UrlClash('en_US', 'contact', $drafted, ['WEB'])], $this->finder()->findDrafted($this->page, ['en_US' => 'contact'], ['WEB']));
    }

    private function finder(): UrlClashFinder
    {
        return new UrlClashFinder(new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'article' => ['model' => Article::class, 'translation' => null, 'resource' => 'app.article'],
            'recipe' => ['model' => Recipe::class, 'translation' => null, 'resource' => 'app.recipe'],
        ]), $this->claims->reveal());
    }

    /**
     * What the query answers: the claims in the locale whose slug the URL holds for their content type, except the
     * entry's own
     *
     * @param list<UrlClaim> $claims
     * @param array<string, string> $slugs
     *
     * @return list<UrlClaim>
     */
    private static function matching(array $claims, string $localeCode, array $slugs, string $exceptUuid): array
    {
        return array_values(array_filter(
            $claims,
            static fn (UrlClaim $claim): bool => $claim->localeCode === $localeCode && ($slugs[$claim->contentType] ?? null) === $claim->slug && $claim->uuid !== $exceptUuid,
        ));
    }

    /**
     * @param list<string> $channelCodes
     */
    private static function claim(string $contentType, string $localeCode, string $slug, array $channelCodes, ?string $uuid = null): UrlClaim
    {
        return new UrlClaim($uuid ?? sprintf('0193a5be-0000-7000-8000-%012d', random_int(0, 999999)), $contentType, $localeCode, $slug, 'Other', $channelCodes);
    }
}
