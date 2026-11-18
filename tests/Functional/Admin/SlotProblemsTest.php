<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Publishing\EntrySettings;
use Setono\SyliusCMSPlugin\Publishing\EntrySettingsSaverInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\SlotsKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Customers;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\Placements;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The warnings about where placements show: a capped slot without a catch-all placement, and a placement on Sylius's own
 * homepage in a channel whose homepage is a CMS page. SlotsKernel caps shop.top at 1. See "Problems" in
 * docs/spec/admin.md, "Capped slots" in docs/spec/targeting.md and "Rendering the homepage" in
 * docs/spec/urls-and-seo.md
 */
final class SlotProblemsTest extends WebTestCase
{
    use Customers;
    use Entries;
    use Placements;

    protected static function getKernelClass(): string
    {
        return SlotsKernel::class;
    }

    /**
     * @test
     */
    public function it_warns_on_each_snippet_in_a_capped_slot_while_none_of_its_placements_is_a_catch_all(): void
    {
        $client = self::signedInClient();
        self::createCustomerGroup('wholesale', 'Wholesale');
        $wholesale = self::createSnippet('Wholesale prices');
        self::setAudience($wholesale, Audience::only('wholesale'));
        self::place($wholesale, 'shop.top');
        self::assertEquals(new ProblemCount(0, 1), self::storedCount($wholesale));

        $crawler = $client->request('GET', self::overview($wholesale));
        $problem = $crawler->filter('[data-section="problems"] [data-problem="capped_slot_without_catch_all"]');
        self::assertCount(1, $problem);
        self::assertSame('warning', $problem->attr('data-severity'));
        self::assertSame(
            '"Every page: the top of the header" is capped at 1 and has no catch-all placement, with no page scope and a published, enabled snippet for everyone, so some pages or shoppers may see nothing there.',
            $problem->filter('.description')->text(),
        );
        self::assertSame(self::overview($wholesale) . '#cms-section-placements', $problem->filter('a')->attr('href'));

        // A published snippet for everyone placed there is the catch-all, which counts the other snippets in the slot again
        $welcome = self::createSnippet('Welcome', published: true);
        self::place($welcome, 'shop.top');
        self::assertEquals(new ProblemCount(), self::storedCount($wholesale));
        self::assertEquals(new ProblemCount(), self::storedCount($welcome));

        // Its audience changed, it no longer is
        self::setAudience($welcome, Audience::except('@guest'));
        self::assertEquals(new ProblemCount(0, 1), self::storedCount($wholesale));
        self::assertEquals(new ProblemCount(0, 1), self::storedCount($welcome));

        // Nor is a slot that isn't capped flagged
        $footer = self::createSnippet('Footer');
        self::setAudience($footer, Audience::only('wholesale'));
        self::place($footer, 'shop.footer');
        self::assertEquals(new ProblemCount(), self::storedCount($footer));
    }

    /**
     * A snippet for everyone with no page scope is a catch-all only while it's published and enabled, so publishing,
     * disabling and enabling it counts the snippets in its capped slots again
     *
     * @test
     */
    public function it_counts_the_snippets_in_a_capped_slot_again_when_the_snippet_of_its_catch_all_is_published_disabled_or_enabled(): void
    {
        self::createCustomerGroup('wholesale', 'Wholesale');
        $wholesale = self::createSnippet('Wholesale prices', published: true);
        self::setAudience($wholesale, Audience::only('wholesale'));
        self::place($wholesale, 'shop.top');
        $welcome = self::createSnippet('Welcome');
        self::place($welcome, 'shop.top');

        // Never published, it isn't the catch-all, wherever it sits
        self::assertEquals(new ProblemCount(0, 1), self::storedCount($wholesale));
        self::assertEquals(new ProblemCount(0, 1), self::storedCount($welcome));

        self::publishEntry($welcome);
        self::assertEquals(new ProblemCount(), self::storedCount($wholesale));
        self::assertEquals(new ProblemCount(), self::storedCount($welcome));

        self::setEnabled($welcome, false);
        self::assertEquals(new ProblemCount(0, 1), self::storedCount($wholesale));
        self::assertEquals(new ProblemCount(0, 1), self::storedCount($welcome));

        self::setEnabled($welcome, true);
        self::assertEquals(new ProblemCount(), self::storedCount($wholesale));
        self::assertEquals(new ProblemCount(), self::storedCount($welcome));
    }

    /**
     * @test
     */
    public function it_warns_on_a_placement_on_sylius_s_own_homepage_while_a_cms_page_is_the_homepage_of_one_of_the_snippet_s_channels(): void
    {
        $client = self::signedInClient();
        $snippet = self::createSnippet('Free shipping', published: true);
        self::place($snippet, 'store_homepage');
        self::place($snippet, 'shop.footer');
        self::assertEquals(new ProblemCount(), self::storedCount($snippet));

        // Making a page the channel's homepage counts the snippets on Sylius's homepage again
        $home = self::createPage('Home', 'home', 'Welcome');
        self::setHomepageOf($home, 'WEB');
        self::assertEquals(new ProblemCount(0, 1), self::storedCount($snippet));

        $crawler = $client->request('GET', self::overview($snippet));
        $problem = $crawler->filter('[data-section="problems"] [data-problem="placement_hidden_by_homepage"]');
        self::assertCount(1, $problem);
        self::assertSame('warning', $problem->attr('data-severity'));
        self::assertSame(
            'The placement in "The store\'s own homepage: above its blocks" doesn\'t show in Setono CMS Test Store, whose homepage is a CMS page, as Sylius\'s own homepage doesn\'t render there.',
            $problem->filter('.description')->text(),
        );

        self::setHomepageOf($home);
        self::assertEquals(new ProblemCount(), self::storedCount($snippet));
    }

    private static function signedInClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');

        return $client;
    }

    private static function overview(SnippetInterface $snippet): string
    {
        return sprintf('/admin/cms/entry/%s', $snippet->getUuid());
    }

    /**
     * Enables or disables the snippet as saving its settings does
     */
    private static function setEnabled(EntryInterface $entry, bool $enabled): void
    {
        $current = EntrySettings::of($entry);

        /** @var EntrySettingsSaverInterface $saver */
        $saver = self::getContainer()->get(EntrySettingsSaverInterface::class);
        $saver->save($entry, new EntrySettings($enabled, $current->dateWindow, $current->channels, $current->audience, $current->homepageOf));
    }

    /**
     * Changes the snippet's audience as saving its settings does
     */
    private static function setAudience(EntryInterface $entry, Audience $audience): void
    {
        $current = EntrySettings::of($entry);

        /** @var EntrySettingsSaverInterface $saver */
        $saver = self::getContainer()->get(EntrySettingsSaverInterface::class);
        $saver->save($entry, new EntrySettings($current->enabled, $current->dateWindow, $current->channels, $audience, $current->homepageOf));
    }
}
