<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Problem\ProblemCountFingerprintInterface;
use Setono\SyliusCMSPlugin\Query\ProblemCountsQueryInterface;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\GreetingKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Catalog;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Crawler;

/**
 * A visibility condition naming a deleted channel or locale, or referring to a deleted record, is a broken reference, and
 * one of a kind the code no longer has a warning. They're counted with the entry's problems, and counted again when
 * what they name is deleted or a deploy removes the kind. See "Problems" in docs/spec/admin.md
 */
final class ConditionProblemsTest extends WebTestCase
{
    use Catalog;
    use Entries;

    private const HEADING = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    private const OTHER_HEADING = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e50';

    /**
     * @test
     */
    public function it_counts_the_entries_that_name_a_deleted_channel_in_a_condition_again_and_no_other(): void
    {
        self::bootKernel();
        self::createChannel('OUTLET');
        $outlet = self::pageWithACondition('Outlet', 'outlet', ['kind' => 'channel', 'negate' => false, 'params' => ['channels' => ['WEB', 'OUTLET']]]);
        $other = self::createPage('Contact', 'contact-us', 'Hello');
        self::query()->store([(int) $other->getId() => new ProblemCount(5, 4)], self::fingerprint());
        self::assertEquals(new ProblemCount(), self::storedCount($outlet));

        self::deleteChannel('OUTLET');

        self::assertEquals(new ProblemCount(1, 0), self::storedCount($outlet));
        self::assertEquals(new ProblemCount(5, 4), self::storedCount($other), 'An entry that doesn\'t name it isn\'t counted again');
    }

    /**
     * @test
     */
    public function it_counts_the_entries_whose_condition_refers_to_a_deleted_product_again(): void
    {
        self::bootKernel();
        $mug = self::createProduct('MUG', 'Mug');
        self::createProduct('CAP', 'Cap');
        $mugs = self::pageWithACondition('Mugs', 'mugs', ['kind' => 'product', 'negate' => true, 'params' => ['products' => ['MUG', 'CAP']]]);
        self::assertEquals(new ProblemCount(), self::storedCount($mugs));

        self::manager()->remove($mug);
        self::manager()->flush();

        self::assertEquals(new ProblemCount(1, 0), self::storedCount($mugs));
    }

    /**
     * The counts were made with other code, so the first admin page counts the entries again, without a command
     *
     * @test
     */
    public function it_shows_a_condition_kind_a_deploy_removed_on_the_next_admin_page(): void
    {
        // The code had the condition kind when the page was saved
        static::$class = GreetingKernel::class;
        self::bootKernel();
        $page = self::pageWithACondition('Members', 'members', ['kind' => 'app_signed_in', 'negate' => false, 'params' => []]);
        self::assertEquals(new ProblemCount(), self::storedCount($page));

        // The deploy removed it
        self::ensureKernelShutdown();
        static::$class = null;
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $crawler = $client->request('GET', '/admin/');

        $content = $crawler->filter('.sylius-admin-menu a[href="/admin/cms"]');
        self::assertSame(['1', 'warning'], [$content->attr('data-cms-problem-count'), $content->attr('data-cms-problems')]);
        self::assertEquals(new ProblemCount(0, 1), self::storedCount($page));
    }

    /**
     * @test
     */
    public function it_lists_the_broken_references_in_conditions_as_errors_and_a_removed_kind_as_a_warning_on_the_overview_page(): void
    {
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $page = self::pageWithBrokenConditions();

        $crawler = $client->request('GET', sprintf('/admin/cms/entry/%s', $page->getUuid()));

        self::assertResponseIsSuccessful();
        $fix = sprintf('/admin/cms/entry/%s/builder?block=%%s', $page->getUuid());
        self::assertSame(
            [
                ['broken_condition_reference', 'error', self::HEADING, 'The channel "OUTLET" in a condition of the Heading block was deleted, so the condition no longer counts it.', sprintf($fix, self::HEADING)],
                ['broken_condition_reference', 'error', self::HEADING, 'The locale "sv_SE" in a condition of the Heading block was deleted, so the condition no longer counts it.', sprintf($fix, self::HEADING)],
                ['broken_condition_reference', 'error', self::OTHER_HEADING, 'Products in a condition of the Heading block refers to a product that was deleted, so the condition no longer counts it.', sprintf($fix, self::OTHER_HEADING)],
                ['unknown_condition_kind', 'warning', self::OTHER_HEADING, 'A condition of the Heading block is of the kind "app_signed_in", which the code no longer has, so the block isn\'t shown.', sprintf($fix, self::OTHER_HEADING)],
            ],
            $crawler->filter('[data-section="problems"] [data-problem]')->each(static fn (Crawler $problem): array => [
                $problem->attr('data-problem'),
                $problem->attr('data-severity'),
                $problem->attr('data-block'),
                $problem->filter('.description')->text(),
                $problem->selectLink('Fix in builder')->attr('href'),
            ]),
        );
    }

    /**
     * A project may remove a condition kind on purpose, so lint reports only the broken references
     *
     * @test
     */
    public function it_reports_the_broken_references_in_conditions_in_setono_cms_lint(): void
    {
        $kernel = self::bootKernel();
        $page = self::pageWithBrokenConditions();

        $tester = new CommandTester((new Application($kernel))->find('setono:cms:lint'));
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString(sprintf('%s published %s The channel "OUTLET" in a condition of the Heading block was deleted', $page->getUuid(), self::HEADING), $display);
        self::assertStringContainsString(sprintf('%s published %s Products in a condition of the Heading block refers to a product that was deleted', $page->getUuid(), self::OTHER_HEADING), $display);
        self::assertStringNotContainsString('app_signed_in', $display);
    }

    /**
     * A published page with a heading naming the channel OUTLET and the locale sv_SE, which the store doesn't have, and
     * another referring to the product HAT, which it doesn't have either, with a condition of a kind it doesn't have
     */
    private static function pageWithBrokenConditions(): EntryInterface
    {
        $heading = self::heading(self::HEADING, 'Outlet');
        $heading->conditions = [
            ['kind' => 'channel', 'negate' => false, 'params' => ['channels' => ['WEB', 'OUTLET']]],
            ['kind' => 'locale', 'negate' => true, 'params' => ['locales' => ['sv_SE']]],
        ];
        $other = self::heading(self::OTHER_HEADING, 'Hats');
        $other->conditions = [
            ['kind' => 'product', 'negate' => false, 'params' => ['products' => ['HAT']]],
            ['kind' => 'app_signed_in', 'negate' => false, 'params' => []],
        ];

        return self::createPageWithBlocks('Outlet', 'outlet', [$heading, $other], published: true);
    }

    /**
     * @param array<string, mixed> $condition
     */
    private static function pageWithACondition(string $title, string $slug, array $condition): EntryInterface
    {
        $heading = self::heading(self::HEADING, $title);
        $heading->conditions = [$condition];

        return self::createPageWithBlocks($title, $slug, [$heading], published: true);
    }

    private static function deleteChannel(string $code): void
    {
        /** @var RepositoryInterface<ChannelInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.channel');
        $channel = $repository->findOneBy(['code' => $code]);
        self::assertNotNull($channel);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.channel');
        $manager->remove($channel);
        $manager->flush();
    }

    private static function query(): ProblemCountsQueryInterface
    {
        /** @var ProblemCountsQueryInterface $query */
        $query = self::getContainer()->get(ProblemCountsQueryInterface::class);

        return $query;
    }

    private static function fingerprint(): string
    {
        /** @var ProblemCountFingerprintInterface $fingerprint */
        $fingerprint = self::getContainer()->get(ProblemCountFingerprintInterface::class);

        return $fingerprint->get();
    }

    private static function manager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');

        return $manager;
    }
}
