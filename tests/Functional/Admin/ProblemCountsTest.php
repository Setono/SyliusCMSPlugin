<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Setono\SyliusCMSPlugin\Model\Entry;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCount;
use Setono\SyliusCMSPlugin\Problem\ProblemCountFingerprintInterface;
use Setono\SyliusCMSPlugin\Problem\ProblemCountProviderInterface;
use Setono\SyliusCMSPlugin\Publishing\PublishSchedulerInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Query\ProblemCountsQueryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Targeting\Audience;
use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\GreetingKernel;
use Setono\SyliusCMSPlugin\Tests\Support\Customers;
use Setono\SyliusCMSPlugin\Tests\Support\Entries;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The lists' problem marker, their Needs attention filter and the Content badge read counts stored with the entries,
 * which a write counts again for the entries it touches, so no admin page goes through every entry. See "Problems" in
 * docs/spec/admin.md
 */
final class ProblemCountsTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use Customers;
    use Entries;

    private const PROMO = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e50';

    /**
     * @test
     */
    public function an_autosave_counts_the_problems_of_the_entry_it_saves_and_no_other_entrys(): void
    {
        self::bootKernel();
        $saved = self::createPageWithBlocks('About us', 'about-us', [new StoredBlock(self::PROMO, 'promo', 1)]);
        $other = self::createPage('Contact', 'contact-us', 'Hello');
        self::assertEquals(new ProblemCount(0, 1), self::storedCount($saved));
        // As if the other one had problems, so a count of it would show
        self::query()->store([(int) $other->getId() => new ProblemCount(5, 4)], self::fingerprint());

        self::manager()->clear();
        $saved = self::entry($saved->getUuid());
        self::saveChanges($saved, [['op' => 'remove_block', 'key' => self::PROMO]]);

        self::assertEquals(new ProblemCount(), self::storedCount($saved));
        self::assertEquals(new ProblemCount(5, 4), self::storedCount($other));
        self::assertFalse(self::manager()->getUnitOfWork()->tryGetById($other->getId(), Entry::class), 'The other entry isn\'t loaded');
    }

    /**
     * Nothing is written when a scheduled publish becomes overdue, so the lists and the Content badge find it as of now
     *
     * @test
     */
    public function a_scheduled_publish_that_becomes_overdue_needs_attention_without_anything_written(): void
    {
        self::mockTime('2026-11-20 10:00:00');
        $client = self::createClient();
        $client->loginUser(self::admin(), 'admin');
        $page = self::createPage('About us', 'about-us', 'Hello');
        /** @var PublishSchedulerInterface $scheduler */
        $scheduler = self::getContainer()->get(PublishSchedulerInterface::class);
        $scheduler->schedule($page, new \DateTimeImmutable('2026-11-20 11:30:00', new \DateTimeZone('UTC')));

        // Four minutes after its time it isn't overdue yet
        self::mockTime('2026-11-20 11:34:59');
        $crawler = $client->request('GET', '/admin/cms/entries/page');
        self::assertNull($crawler->filter('.sylius-admin-menu a[href="/admin/cms"]')->attr('data-cms-problem-count'));
        self::assertCount(0, $crawler->filter('[data-column="problems"] a'));

        self::mockTime('2026-11-20 11:35:00');
        $crawler = $client->request('GET', '/admin/cms/entries/page');

        $content = $crawler->filter('.sylius-admin-menu a[href="/admin/cms"]');
        self::assertSame(['1', 'error'], [$content->attr('data-cms-problem-count'), $content->attr('data-cms-problems')]);
        $marker = $crawler->filter('[data-column="problems"] a');
        self::assertSame(['1', '1 error, no warnings'], [trim($marker->text()), $marker->attr('title')]);

        $crawler = $client->request('GET', '/admin/cms/entries/page?' . http_build_query(['criteria' => ['status' => 'needs_attention']]));
        self::assertSame([$page->getUuid()], $crawler->filter('table tbody [data-entry]')->each(static fn (Crawler $title): ?string => $title->attr('data-entry')));
    }

    /**
     * The counts were made with other code, so the first admin page counts the entries again, without a command
     *
     * @test
     */
    public function a_block_type_a_deploy_removed_shows_on_the_next_admin_page(): void
    {
        // The code had the Greeting block type when the page was saved
        static::$class = GreetingKernel::class;
        self::bootKernel();
        $greeting = new StoredBlock(self::PROMO, 'greeting', 1);
        $greeting->setValue('text', 'en_US', 'Hello');
        $page = self::createPageWithBlocks('About us', 'about-us', [$greeting]);
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
     * See "Deleted customer groups" in docs/spec/targeting.md
     *
     * @test
     */
    public function deleting_a_customer_group_counts_the_entries_that_name_it_again_and_no_other(): void
    {
        self::bootKernel();
        self::createCustomerGroup('wholesale', 'Wholesale');
        $inAudience = self::createPage('Prices', 'prices', 'Hello', published: true);
        self::channelsAndAudience()->setAudience($inAudience, Audience::only('wholesale'));
        self::manager()->flush();
        $inDraft = self::createPage('Offers', 'offers', 'Hello', published: true);
        self::draftWithACondition($inDraft, ['kind' => 'customer_group', 'negate' => false, 'params' => ['groups' => ['retail', 'wholesale']]]);
        $other = self::createPage('Contact', 'contact-us', 'Hello');
        self::query()->store([(int) $other->getId() => new ProblemCount(5, 4)], self::fingerprint());
        self::assertEquals(new ProblemCount(), self::storedCount($inAudience));
        self::assertEquals(new ProblemCount(1, 0), self::storedCount($inDraft), 'The group "retail" doesn\'t exist');

        self::deleteCustomerGroup('wholesale');

        self::assertEquals(new ProblemCount(1, 0), self::storedCount($inAudience));
        self::assertEquals(new ProblemCount(2, 0), self::storedCount($inDraft));
        self::assertEquals(new ProblemCount(5, 4), self::storedCount($other), 'An entry that doesn\'t name it isn\'t counted again');

        // Added again with its code, it's no longer missing
        self::createCustomerGroup('wholesale', 'Wholesale');

        self::assertEquals(new ProblemCount(), self::storedCount($inAudience));
        self::assertEquals(new ProblemCount(1, 0), self::storedCount($inDraft));
    }

    /**
     * Saves a draft of the published entry whose heading has the condition
     *
     * @param array<string, mixed> $condition
     */
    private static function draftWithACondition(EntryInterface $entry, array $condition): void
    {
        /** @var WorkingCopyManagerInterface $workingCopies */
        $workingCopies = self::getContainer()->get(WorkingCopyManagerInterface::class);
        $workingCopy = $workingCopies->getWorkingCopy($entry);
        $heading = $workingCopy->getBlockTree()->blocks[0];
        $heading->conditions = [$condition];
        $workingCopy->setBlockTree(new StoredTree(null, [$heading]));
        $workingCopies->save($entry, $workingCopy);
    }

    private static function deleteCustomerGroup(string $code): void
    {
        /** @var RepositoryInterface<CustomerGroupInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.customer_group');
        $group = $repository->findOneBy(['code' => $code]);
        self::assertNotNull($group);

        /** @var ObjectManager $manager */
        $manager = self::getContainer()->get('sylius.manager.customer_group');
        $manager->remove($group);
        $manager->flush();
    }

    private static function storedCount(EntryInterface $entry): ProblemCount
    {
        /** @var ProblemCountProviderInterface $provider */
        $provider = self::getContainer()->get(ProblemCountProviderInterface::class);

        return $provider->getCounts([$entry])[$entry->getUuid()];
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

    private static function entry(string $uuid): EntryInterface
    {
        /** @var EntryRepositoryInterface $entries */
        $entries = self::getContainer()->get(EntryRepositoryInterface::class);
        $entry = $entries->findOneByUuid($uuid);
        self::assertNotNull($entry);

        return $entry;
    }

    private static function manager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $manager */
        $manager = self::getContainer()->get('setono_sylius_cms.manager.page');

        return $manager;
    }
}
