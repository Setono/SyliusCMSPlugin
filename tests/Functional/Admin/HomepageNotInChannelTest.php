<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Publishing\EntrySettings;
use Setono\SyliusCMSPlugin\Publishing\EntrySettingsSaverInterface;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A page only becomes the homepage of a channel it's in. One that leaves the channel stays its homepage, which shoppers
 * there never see, so the admin warns about it. See "Homepage" in docs/spec/urls-and-seo.md and "Problems" in
 * docs/spec/admin.md
 */
final class HomepageNotInChannelTest extends WebTestCase
{
    use Entries;

    /**
     * @test
     */
    public function it_warns_on_a_page_that_leaves_a_channel_it_is_the_homepage_of_until_it_is_back_in_it(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $page = self::createPage('Home', 'home', 'Welcome', channels: ['WEB']);
        self::setHomepageOf($page, 'WEB');
        self::assertEquals(new ProblemCount(), self::storedCount($page));

        self::setChannels($page);

        self::assertEquals(new ProblemCount(0, 1), self::storedCount($page));
        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));
        $problem = $crawler->filter('[data-section="problems"] [data-problem="homepage_not_in_channel"]');
        self::assertCount(1, $problem);
        self::assertSame('warning', $problem->attr('data-severity'));
        self::assertSame(
            'It\'s the homepage of Setono CMS Test Store, but isn\'t in that channel, so shoppers there see the store\'s own homepage. Put it back in the channel, or take the channel out of "Homepage of".',
            $problem->filter('.description')->text(),
        );
        self::assertSame(sprintf('/admin/cms/entry/%s/builder', $page->getUuid()), $problem->filter('a')->attr('href'));

        self::setChannels(self::reloadPage($page), 'WEB');

        self::assertEquals(new ProblemCount(), self::storedCount($page));
    }

    /**
     * @test
     */
    public function it_stops_warning_once_the_page_is_no_longer_the_channels_homepage(): void
    {
        $page = self::createPage('Home', 'home', 'Welcome', channels: ['WEB']);
        self::setHomepageOf($page, 'WEB');
        self::setChannels($page);
        self::assertEquals(new ProblemCount(0, 1), self::storedCount($page));

        self::setHomepageOf(self::reloadPage($page));

        self::assertEquals(new ProblemCount(), self::storedCount($page));

        // Another page made the channel's homepage takes it off the page, which is counted again
        self::setChannels(self::reloadPage($page), 'WEB');
        self::setHomepageOf(self::reloadPage($page), 'WEB');
        self::setChannels(self::reloadPage($page));
        self::assertEquals(new ProblemCount(0, 1), self::storedCount($page));

        self::setHomepageOf(self::createPage('Sale', 'sale', 'Everything must go', channels: ['WEB']), 'WEB');

        self::assertEquals(new ProblemCount(), self::storedCount($page));
    }

    /**
     * Changes the page's channels as saving its settings does
     */
    private static function setChannels(PageInterface $page, string ...$channelCodes): void
    {
        $current = EntrySettings::of($page);

        /** @var EntrySettingsSaverInterface $saver */
        $saver = self::getContainer()->get(EntrySettingsSaverInterface::class);
        $saver->save($page, new EntrySettings($current->enabled, $current->dateWindow, $channelCodes, $current->audience, $current->homepageOf));
    }
}
