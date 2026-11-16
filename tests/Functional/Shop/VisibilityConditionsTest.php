<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Shop;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Tests\Support\Customers;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The shop hides blocks whose visibility conditions don't match the shopper or the page, before their templates
 * render. See "Visibility conditions" and "Condition kinds" in docs/spec/targeting.md
 */
final class VisibilityConditionsTest extends WebTestCase
{
    use Customers;
    use Entries;

    /**
     * @test
     */
    public function it_hides_blocks_by_channel(): void
    {
        $client = self::createClient();
        self::createChannel('B2B', 'Wholesale', 'b2b.localhost');
        self::createPageWithBlocks('About us', 'about-us', [
            self::headingIf('0193a5be-0000-7000-8000-000000000001', 'Everywhere'),
            self::headingIf('0193a5be-0000-7000-8000-000000000002', 'Only in the web store', ['kind' => 'channel', 'negate' => false, 'params' => ['channels' => ['WEB']]]),
            self::headingIf('0193a5be-0000-7000-8000-000000000003', 'Not in the web store', ['kind' => 'channel', 'negate' => true, 'params' => ['channels' => ['WEB']]]),
        ], published: true);

        self::assertSame(['Everywhere', 'Only in the web store'], self::headings($client->request('GET', 'http://localhost/en_US/about-us')));
        self::assertSame(['Everywhere', 'Not in the web store'], self::headings($client->request('GET', 'http://b2b.localhost/en_US/about-us')));
    }

    /**
     * @test
     */
    public function it_hides_blocks_by_locale(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'da_DK', 'de_DE');
        $page = self::createPageWithBlocks('About us', 'about-us', [
            self::headingIf('0193a5be-0000-7000-8000-000000000001', 'Not in Danish', ['kind' => 'locale', 'negate' => true, 'params' => ['locales' => ['da_DK']]]),
            self::headingIf('0193a5be-0000-7000-8000-000000000002', 'In Danish or German', ['kind' => 'locale', 'negate' => false, 'params' => ['locales' => ['da_DK', 'de_DE']]]),
        ], published: true);
        self::translate($page, 'da_DK', 'Om os', 'om-os');
        self::translate($page, 'de_DE', 'Über uns', 'uber-uns');

        self::assertSame(['Not in Danish'], self::headings($client->request('GET', '/en_US/about-us')));
        self::assertSame(['In Danish or German'], self::headings($client->request('GET', '/da_DK/om-os')));
        self::assertSame(['Not in Danish', 'In Danish or German'], self::headings($client->request('GET', '/de_DE/uber-uns')));
    }

    /**
     * @test
     */
    public function it_hides_blocks_outside_their_date_window_or_inside_it_when_negated(): void
    {
        $client = self::createClient();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $window = static fn (\DateTimeImmutable $from, \DateTimeImmutable $until, bool $negate = false): array => [
            'kind' => 'date_window',
            'negate' => $negate,
            'params' => ['from' => $from->format('Y-m-d\TH:i:s\Z'), 'until' => $until->format('Y-m-d\TH:i:s\Z')],
        ];
        self::createPageWithBlocks('Campaign', 'campaign', [
            self::headingIf('0193a5be-0000-7000-8000-000000000001', 'Now', $window($now->modify('-1 day'), $now->modify('+1 day'))),
            self::headingIf('0193a5be-0000-7000-8000-000000000002', 'Next week', $window($now->modify('+7 days'), $now->modify('+8 days'))),
            self::headingIf('0193a5be-0000-7000-8000-000000000003', 'Last week', $window($now->modify('-8 days'), $now->modify('-7 days'))),
            self::headingIf('0193a5be-0000-7000-8000-000000000004', 'Except next week', $window($now->modify('+7 days'), $now->modify('+8 days'), negate: true)),
            self::headingIf('0193a5be-0000-7000-8000-000000000005', 'Except now', $window($now->modify('-1 day'), $now->modify('+1 day'), negate: true)),
            self::headingIf('0193a5be-0000-7000-8000-000000000006', 'Until yesterday', ['kind' => 'date_window', 'negate' => false, 'params' => ['until' => $now->modify('-1 day')->format('Y-m-d\TH:i:s\Z')]]),
        ], published: true);

        self::assertSame(['Now', 'Except next week'], self::headings($client->request('GET', '/en_US/campaign')));
    }

    /**
     * Guests and customers signed in without a group count as groups of their own
     *
     * @test
     */
    public function it_hides_blocks_by_customer_group_for_a_guest_a_group_and_no_group(): void
    {
        $client = self::createClient();
        self::createCustomerGroup('wholesale', 'Wholesale');
        self::createCustomerGroup('retail', 'Retail');
        $wholesaler = self::createShopUser('wholesaler@example.com', 'wholesale');
        $retailer = self::createShopUser('retailer@example.com', 'retail');
        $withoutGroup = self::createShopUser('shopper@example.com');
        $groups = static fn (array $groups, bool $negate = false): array => ['kind' => 'customer_group', 'negate' => $negate, 'params' => ['groups' => $groups]];
        self::createPageWithBlocks('Prices', 'prices', [
            self::headingIf('0193a5be-0000-7000-8000-000000000001', 'Sign in for prices', $groups(['@guest'])),
            self::headingIf('0193a5be-0000-7000-8000-000000000002', 'Wholesale prices', $groups(['wholesale'])),
            self::headingIf('0193a5be-0000-7000-8000-000000000003', 'Join a group', $groups(['@no_group'])),
            self::headingIf('0193a5be-0000-7000-8000-000000000004', 'Not for wholesalers', $groups(['wholesale'], negate: true)),
        ], published: true);

        self::assertSame(['Sign in for prices', 'Not for wholesalers'], self::headings($client->request('GET', '/en_US/prices')));

        $client->loginUser($wholesaler, 'shop');
        self::assertSame(['Wholesale prices'], self::headings($client->request('GET', '/en_US/prices')));

        $client->loginUser($retailer, 'shop');
        self::assertSame(['Not for wholesalers'], self::headings($client->request('GET', '/en_US/prices')));

        $client->loginUser($withoutGroup, 'shop');
        self::assertSame(['Join a group', 'Not for wholesalers'], self::headings($client->request('GET', '/en_US/prices')));
    }

    /**
     * A block's conditions combine with AND
     *
     * @test
     */
    public function it_shows_a_block_only_when_all_its_conditions_match(): void
    {
        $client = self::createClient();
        self::addLocales('WEB', 'da_DK');
        $page = self::createPageWithBlocks('About us', 'about-us', [self::headingIf(
            '0193a5be-0000-7000-8000-000000000001',
            'Danish guests',
            ['kind' => 'locale', 'negate' => false, 'params' => ['locales' => ['da_DK']]],
            ['kind' => 'customer_group', 'negate' => false, 'params' => ['groups' => ['@guest']]],
        )], published: true);
        self::translate($page, 'da_DK', 'Om os', 'om-os');

        self::assertSame([], self::headings($client->request('GET', '/en_US/about-us')));
        self::assertSame(['Danish guests'], self::headings($client->request('GET', '/da_DK/om-os')));

        $client->loginUser(self::createShopUser('shopper@example.com'), 'shop');
        self::assertSame([], self::headings($client->request('GET', '/da_DK/om-os')));
    }

    /**
     * @test
     */
    public function a_hidden_parent_hides_its_children(): void
    {
        $client = self::createClient();
        $group = self::blockIf('0193a5be-0000-7000-8000-000000000001', 'group', ['kind' => 'customer_group', 'negate' => false, 'params' => ['groups' => ['@no_group']]]);
        $group->children = [self::heading('0193a5be-0000-7000-8000-000000000002', 'Inside')];
        self::createPageWithBlocks('About us', 'about-us', [$group, self::heading('0193a5be-0000-7000-8000-000000000003', 'Outside')], published: true);

        $crawler = $client->request('GET', '/en_US/about-us');

        self::assertSame(['Outside'], self::headings($crawler));
        self::assertCount(0, $crawler->filter('.cms-group'));
    }

    /**
     * @test
     */
    public function a_first_match_block_shows_only_its_first_child_whose_conditions_match_with_no_markup_of_its_own(): void
    {
        $client = self::createClient();
        self::createCustomerGroup('wholesale', 'Wholesale');
        $firstMatch = new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'first_match', 1, children: [
            self::headingIf('0193a5be-0000-7000-8000-000000000002', 'Wholesale banner', ['kind' => 'customer_group', 'negate' => false, 'params' => ['groups' => ['wholesale']]]),
            self::headingIf('0193a5be-0000-7000-8000-000000000003', 'Guest banner', ['kind' => 'customer_group', 'negate' => false, 'params' => ['groups' => ['@guest']]]),
            self::heading('0193a5be-0000-7000-8000-000000000004', 'Default banner'),
        ]);
        self::createPageWithBlocks('Home', 'home', [$firstMatch], published: true);

        $crawler = $client->request('GET', '/en_US/home');
        self::assertSame(['Guest banner'], self::headings($crawler));
        self::assertSame('<h2 class="cms-heading">Guest banner</h2>', trim($crawler->filter('.cms-tree')->html()));

        $client->loginUser(self::createShopUser('wholesaler@example.com', 'wholesale'), 'shop');
        self::assertSame(['Wholesale banner'], self::headings($client->request('GET', '/en_US/home')));

        $client->loginUser(self::createShopUser('shopper@example.com'), 'shop');
        self::assertSame(['Default banner'], self::headings($client->request('GET', '/en_US/home')));
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
     * @param array<string, mixed> ...$conditions
     */
    private static function blockIf(string $key, string $type, array ...$conditions): StoredBlock
    {
        return new StoredBlock($key, $type, 1, conditions: array_values($conditions));
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

    private static function translate(PageInterface $page, string $localeCode, string $title, string $slug): void
    {
        $translation = new PageTranslation();
        $translation->setLocale($localeCode);
        $translation->setTitle($title);
        $translation->setSlug($slug);
        $page->addTranslation($translation);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();
    }
}
