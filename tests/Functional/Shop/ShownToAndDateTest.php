<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Publishing\PublishSchedulerInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Targeting\DateWindow;
use Setono\SyliusCMSPlugin\Tests\Support\Customers;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tests\Support\ScheduledPublishes;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The preview token's Shown to and Date set the customer group value and the time the page renders for, so every
 * condition, audience and visible-entries query on it follows them, while the edited entry ignores its own visibility.
 * See "Shown to and Date" in docs/spec/targeting.md and "Preview mode" in docs/spec/builder.md
 */
final class ShownToAndDateTest extends WebTestCase
{
    use Customers;
    use Entries;
    use ScheduledPublishes;

    private const EVERYONE = '0193a5be-0000-7000-8000-000000000001';

    private const WHOLESALE = '0193a5be-0000-7000-8000-000000000002';

    private const GUESTS = '0193a5be-0000-7000-8000-000000000003';

    private const NEXT_WEEK = '0193a5be-0000-7000-8000-000000000004';

    private const UNTIL_TOMORROW = '0193a5be-0000-7000-8000-000000000005';

    /**
     * @test
     */
    public function it_renders_a_block_only_for_wholesale_with_a_token_shown_to_wholesale(): void
    {
        $client = self::createClient();
        self::createCustomerGroup('wholesale', 'Wholesale');
        $page = self::createPageWithBlocks('Prices', 'prices', self::groupBlocks());

        self::assertSame(['Everyone', 'Wholesale prices'], self::headings($client->request('GET', '/en_US/prices?_cms_preview=' . self::previewToken($page, shownTo: 'wholesale'))));
        self::assertSame(['Everyone', 'Sign in for prices'], self::headings($client->request('GET', '/en_US/prices?_cms_preview=' . self::previewToken($page, shownTo: '@guest'))));
    }

    /**
     * Shown to is the customer group value, whoever is signed in to the shop
     *
     * @test
     */
    public function it_renders_for_guests_with_a_token_shown_to_guests_while_a_wholesale_customer_is_signed_in(): void
    {
        $client = self::createClient();
        self::createCustomerGroup('wholesale', 'Wholesale');
        $client->loginUser(self::createShopUser('wholesaler@example.com', 'wholesale'), 'shop');
        $page = self::createPageWithBlocks('Prices', 'prices', self::groupBlocks());

        self::assertSame(['Everyone', 'Wholesale prices'], self::headings($client->request('GET', '/en_US/prices?_cms_preview=' . self::previewToken($page))), 'Without Shown to, the token shows the page to the shopper');
        self::assertSame(['Everyone', 'Sign in for prices'], self::headings($client->request('GET', '/en_US/prices?_cms_preview=' . self::previewToken($page, shownTo: '@guest'))));
    }

    /**
     * @test
     */
    public function it_shows_a_block_on_the_future_date_its_window_starts(): void
    {
        $client = self::createClient();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $page = self::createPageWithBlocks('Campaign', 'campaign', self::dateBlocks($now));

        self::assertSame(['Everyone', 'Until tomorrow'], self::headings($client->request('GET', '/en_US/campaign?_cms_preview=' . self::previewToken($page))), 'Without a Date, the token shows the page now');
        self::assertSame(['Everyone', 'Next week'], self::headings($client->request('GET', '/en_US/campaign?_cms_preview=' . self::previewToken($page, date: $now->modify('+7 days')))));
    }

    /**
     * @test
     */
    public function it_renders_a_disabled_edited_entry_outside_its_audience_and_date_window_while_its_blocks_follow_shown_to_and_date(): void
    {
        $client = self::createClient();
        self::createCustomerGroup('wholesale', 'Wholesale');
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $page = self::createPageWithBlocks('Prices', 'prices', [...self::groupBlocks(), ...array_slice(self::dateBlocks($now), 1)], published: true);
        $page->setEnabled(false);
        $page->setDateWindow(new DateWindow(null, $now->modify('-1 day')));
        self::channelsAndAudience()->setAudience($page, Audience::only('wholesale'));
        self::flush();

        $client->request('GET', '/en_US/prices');
        self::assertResponseStatusCodeSame(404);

        self::assertSame(['Everyone', 'Sign in for prices', 'Next week'], self::headings($client->request('GET', '/en_US/prices?_cms_preview=' . self::previewToken($page, shownTo: '@guest', date: $now->modify('+7 days')))));
    }

    /**
     * Audiences and the visible-entries query follow Shown to, so the preview shows other entries as that shopper sees them
     *
     * @test
     */
    public function it_shows_another_entry_in_the_preview_when_its_audience_allows_shown_to(): void
    {
        $client = self::createClient();
        self::createCustomerGroup('wholesale', 'Wholesale');
        $edited = self::createPage('About us', 'about-us', 'Hello');
        $other = self::createPage('Wholesale terms', 'wholesale-terms', 'Terms', published: true);
        self::channelsAndAudience()->setAudience($other, Audience::only('wholesale'));
        self::flush();

        $client->request('GET', '/en_US/wholesale-terms?_cms_preview=' . self::previewToken($edited, shownTo: 'wholesale'));
        self::assertResponseStatusCodeSame(200);

        $client->request('GET', '/en_US/wholesale-terms?_cms_preview=' . self::previewToken($edited, shownTo: '@guest'));
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * The visible-entries query follows Date, so the preview shows other entries as they show then
     *
     * @test
     */
    public function it_shows_another_entry_in_the_preview_when_its_date_window_contains_the_date(): void
    {
        $client = self::createClient();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $edited = self::createPage('About us', 'about-us', 'Hello');
        $other = self::createPage('Christmas', 'christmas', 'Ho ho ho', published: true);
        $other->setDateWindow(new DateWindow($now->modify('+7 days'), $now->modify('+8 days')));
        self::flush();

        $client->request('GET', '/en_US/christmas?_cms_preview=' . self::previewToken($edited));
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/en_US/christmas?_cms_preview=' . self::previewToken($edited, date: $now->modify('+7 days')));
        self::assertResponseStatusCodeSame(200);
    }

    /**
     * The forced Date applies to date windows only: other entries show their published content, and an entry that was
     * never published stays hidden, whatever the date
     *
     * @test
     */
    public function it_applies_the_date_to_date_windows_only_and_publishes_nothing(): void
    {
        $client = self::createClient();
        $nextWeek = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+7 days');
        $edited = self::createPage('About us', 'about-us', 'Hello');
        self::createPage('Imprint', 'imprint', 'Who we are');
        $other = self::createPage('Shipping', 'shipping', 'We ship', published: true);
        /** @var WorkingCopyManagerInterface $workingCopies */
        $workingCopies = self::getContainer()->get(WorkingCopyManagerInterface::class);
        $workingCopy = $workingCopies->getWorkingCopy($other);
        $workingCopy->setBlockTree(new StoredTree($workingCopy->getBlockTree()->layout, [self::heading('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'We ship faster')]));
        $workingCopies->save($other, $workingCopy);

        // A publish scheduled for tomorrow, which the scheduled publish's worker runs by the real clock, not by the preview's Date
        $scheduled = self::createPage('Returns', 'returns', 'Return within 14 days', published: true);
        $workingCopy = $workingCopies->getWorkingCopy($scheduled);
        $workingCopy->setBlockTree(new StoredTree($workingCopy->getBlockTree()->layout, [self::heading('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'Return within 30 days')]));
        $workingCopies->save($scheduled, $workingCopy);
        /** @var PublishSchedulerInterface $scheduler */
        $scheduler = self::getContainer()->get(PublishSchedulerInterface::class);
        $scheduler->schedule($scheduled, (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+1 day'));

        $client->request('GET', '/en_US/imprint?_cms_preview=' . self::previewToken($edited, date: $nextWeek));
        self::assertResponseStatusCodeSame(404);

        self::assertSame(['We ship'], self::headings($client->request('GET', '/en_US/shipping?_cms_preview=' . self::previewToken($edited, date: $nextWeek))));
        self::assertSame(['Return within 14 days'], self::headings($client->request('GET', '/en_US/returns?_cms_preview=' . self::previewToken($edited, date: $nextWeek))));
        $scheduledPublish = self::scheduledPublishOf($scheduled);
        self::assertNotNull($scheduledPublish, 'It still waits');
        self::assertFalse($scheduledPublish->isFailed());
    }

    /**
     * @return list<StoredBlock>
     */
    private static function groupBlocks(): array
    {
        return [
            self::heading(self::EVERYONE, 'Everyone'),
            self::headingIf(self::WHOLESALE, 'Wholesale prices', ['kind' => 'customer_group', 'negate' => false, 'params' => ['groups' => ['wholesale']]]),
            self::headingIf(self::GUESTS, 'Sign in for prices', ['kind' => 'customer_group', 'negate' => false, 'params' => ['groups' => ['@guest']]]),
        ];
    }

    /**
     * @return list<StoredBlock>
     */
    private static function dateBlocks(\DateTimeImmutable $now): array
    {
        $at = static fn (string $modifier): string => $now->modify($modifier)->format('Y-m-d\TH:i:s\Z');

        return [
            self::heading(self::EVERYONE, 'Everyone'),
            self::headingIf(self::NEXT_WEEK, 'Next week', ['kind' => 'date_window', 'negate' => false, 'params' => ['from' => $at('+7 days'), 'until' => $at('+8 days')]]),
            self::headingIf(self::UNTIL_TOMORROW, 'Until tomorrow', ['kind' => 'date_window', 'negate' => false, 'params' => ['until' => $at('+1 day')]]),
        ];
    }

    /**
     * @param array<string, mixed> ...$conditions
     */
    private static function headingIf(string $key, string $text, array ...$conditions): StoredBlock
    {
        $heading = self::heading($key, $text);
        $heading->conditions = array_values($conditions);

        return $heading;
    }

    /**
     * @return list<string>
     */
    private static function headings(Crawler $crawler): array
    {
        self::assertResponseIsSuccessful();

        $headings = [];
        foreach ($crawler->filter('.cms-tree .cms-heading') as $heading) {
            $headings[] = $heading->textContent;
        }

        return $headings;
    }

    private static function flush(): void
    {
        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();
    }
}
