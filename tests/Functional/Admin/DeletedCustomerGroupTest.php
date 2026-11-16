<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\PageInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Tests\Support\Customers;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Crawler;

/**
 * A deleted customer group leaves a dangling code, which matches nobody, and the admin flags it as a broken reference.
 * See "Deleted customer groups" in docs/spec/targeting.md and "Problems" in docs/spec/admin.md
 */
final class DeletedCustomerGroupTest extends WebTestCase
{
    use Customers;
    use Entries;

    private const ONLY_WHOLESALE = '0193a5be-0000-7000-8000-000000000001';

    private const NOT_WHOLESALE = '0193a5be-0000-7000-8000-000000000002';

    /**
     * @test
     */
    public function a_deleted_group_in_a_condition_or_the_audience_is_flagged_as_an_error_on_the_overview_page(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $page = self::pageForWholesalers();
        self::deleteCustomerGroup('wholesale');

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        self::assertResponseIsSuccessful();
        $problems = $crawler->filter('[data-section="problems"] [data-problem]');
        self::assertSame(
            [
                ['deleted_customer_group', 'error', null, 'The customer group "wholesale" in the audience was deleted, so the audience no longer counts anyone through it.', sprintf('/admin/cms/entry/%s/builder', $page->getUuid())],
                ['deleted_customer_group', 'error', self::ONLY_WHOLESALE, 'The customer group "wholesale" in a condition of the Heading block was deleted, so the condition no longer matches anyone through it.', sprintf('/admin/cms/entry/%s/builder?block=%s', $page->getUuid(), self::ONLY_WHOLESALE)],
                ['deleted_customer_group', 'error', self::NOT_WHOLESALE, 'The customer group "wholesale" in a condition of the Heading block was deleted, so the condition no longer matches anyone through it.', sprintf('/admin/cms/entry/%s/builder?block=%s', $page->getUuid(), self::NOT_WHOLESALE)],
            ],
            $problems->each(static fn (Crawler $problem): array => [
                $problem->attr('data-problem'),
                $problem->attr('data-severity'),
                $problem->attr('data-block'),
                $problem->filter('.description')->text(),
                $problem->selectLink('Fix in builder')->attr('href'),
            ]),
        );
    }

    /**
     * @test
     */
    public function a_group_that_still_exists_raises_no_problem(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $page = self::pageForWholesalers();

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-section="problems"]'));
    }

    /**
     * "Is one of" no longer matches through it, and "is not one of" no longer excludes anyone through it
     *
     * @test
     */
    public function a_deleted_groups_code_matches_nobody_in_the_shop(): void
    {
        $client = self::createClient();
        self::createPageWithBlocks('Prices', 'prices', [
            self::forWholesale(self::ONLY_WHOLESALE, 'Wholesale prices', negate: false),
            self::forWholesale(self::NOT_WHOLESALE, 'Retail prices', negate: true),
        ], published: true);
        self::createCustomerGroup('wholesale', 'Wholesale');
        self::deleteCustomerGroup('wholesale');

        $client->request('GET', '/en_US/prices');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.cms-tree .cms-heading', 'Retail prices');
        self::assertSelectorCount(1, '.cms-tree .cms-heading');
    }

    /**
     * @test
     */
    public function setono_cms_lint_reports_it_as_a_broken_reference(): void
    {
        $page = self::pageForWholesalers();
        self::deleteCustomerGroup('wholesale');

        $application = new Application(self::$kernel ?? self::bootKernel());
        $tester = new CommandTester($application->find('setono:cms:lint'));
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString(sprintf('%s published %s The customer group "wholesale" in a condition of the Heading block was deleted', $page->getUuid(), self::ONLY_WHOLESALE), $display);
        self::assertStringContainsString(sprintf('%s published The customer group "wholesale" in the audience was deleted', $page->getUuid()), $display);
    }

    private static function pageForWholesalers(): PageInterface
    {
        self::createCustomerGroup('wholesale', 'Wholesale');
        $page = self::createPageWithBlocks('Prices', 'prices', [
            self::forWholesale(self::ONLY_WHOLESALE, 'Wholesale prices', negate: false),
            self::forWholesale(self::NOT_WHOLESALE, 'Retail prices', negate: true),
        ], published: true);
        self::channelsAndAudience()->setAudience($page, Audience::only('wholesale', '@guest'));

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');
        $manager->flush();

        return $page;
    }

    private static function forWholesale(string $key, string $text, bool $negate): StoredBlock
    {
        $heading = self::heading($key, $text);
        $heading->conditions = [['kind' => 'customer_group', 'negate' => $negate, 'params' => ['groups' => ['wholesale']]]];

        return $heading;
    }

    private static function deleteCustomerGroup(string $code): void
    {
        /** @var \Sylius\Resource\Doctrine\Persistence\RepositoryInterface<\Sylius\Component\Customer\Model\CustomerGroupInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.customer_group');
        $group = $repository->findOneBy(['code' => $code]);
        self::assertNotNull($group);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.customer_group');
        $manager->remove($group);
        $manager->flush();
    }
}
