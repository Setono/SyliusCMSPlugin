<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Publishing;

use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\EntryFormat\EntryDenormalizer;
use Setono\SyliusCMSPlugin\EntryFormat\EntryNormalizer;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\Draft;
use Setono\SyliusCMSPlugin\Model\DraftInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManager;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Teaser;
use Setono\SyliusCMSPlugin\Tests\Unit\Upgrade\Fixture\RenameField;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Upgrade\StoredDataUpgrader;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

final class WorkingCopyManagerTest extends TestCase
{
    use ProphecyTrait;

    private const KEY = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f';

    /** @var ObjectProphecy<DraftRepositoryInterface> */
    private ObjectProphecy $drafts;

    /** @var ObjectProphecy<ObjectManager> */
    private ObjectProphecy $manager;

    protected function setUp(): void
    {
        $this->drafts = $this->prophesize(DraftRepositoryInterface::class);
        $this->manager = $this->prophesize(ObjectManager::class);
    }

    /**
     * @test
     */
    public function it_makes_a_never_published_entry_its_own_working_copy_and_writes_its_rows_on_save(): void
    {
        $page = self::page('Hello');

        $workingCopy = $this->workingCopies()->getWorkingCopy($page);
        self::assertSame($page, $workingCopy);

        $this->manager->persist(Argument::any())->shouldNotBeCalled();
        $this->manager->flush()->shouldBeCalledOnce();

        $this->workingCopies()->save($page, $workingCopy);
    }

    /**
     * @test
     */
    public function it_makes_a_copy_of_the_rows_the_working_copy_of_a_published_entry_without_a_draft(): void
    {
        $page = self::page('Hello', published: true);
        $this->drafts->findOneByEntry($page)->willReturn(null);

        $workingCopy = $this->workingCopies()->getWorkingCopy($page);
        $workingCopy->setBlockTree(new StoredTree(null, []));

        self::assertNotSame($page, $workingCopy);
        self::assertSame($page->getUuid(), $workingCopy->getUuid());
        self::assertSame('About us', $workingCopy->getTranslation('en_US')->getTitle());
        self::assertCount(1, $page->getBlockTree()->blocks);
    }

    /**
     * @test
     */
    public function it_creates_the_draft_on_the_first_save_after_a_publish_and_leaves_the_rows(): void
    {
        $page = self::page('Hello', published: true);
        $rows = self::json($page);
        $changedAt = $page->getChangedAt();
        $this->drafts->findOneByEntry($page)->willReturn(null);

        $workingCopy = $this->workingCopies()->getWorkingCopy($page);
        $workingCopy->setBlockTree(self::tree('Hello world'));

        $this->manager->persist(Argument::that(static fn (mixed $draft): bool => $draft instanceof DraftInterface && $page === $draft->getEntry() && self::json($workingCopy) === $draft->getContent()))->shouldBeCalledOnce();
        $this->manager->flush()->shouldBeCalledOnce();

        $this->workingCopies()->save($page, $workingCopy);

        self::assertSame($rows, self::json($page));
        self::assertSame($changedAt, $page->getChangedAt());
    }

    /**
     * @test
     */
    public function it_makes_the_draft_of_a_published_entry_its_working_copy_and_updates_it_on_save(): void
    {
        $page = self::page('Hello', published: true);
        $draft = $this->draft($page, 'Hello world');
        $this->drafts->findOneByEntry($page)->willReturn($draft);

        $workingCopy = $this->workingCopies()->getWorkingCopy($page);
        self::assertSame(['en_US' => 'Hello world'], $workingCopy->getBlockTree()->blocks[0]->fields['text'] ?? null);
        self::assertSame(['en_US' => 'Hello'], $page->getBlockTree()->blocks[0]->fields['text'] ?? null);

        $workingCopy->setBlockTree(self::tree('Goodbye'));
        $this->manager->persist($draft)->shouldBeCalledOnce();
        $this->manager->flush()->shouldBeCalledOnce();

        $this->workingCopies()->save($page, $workingCopy);

        self::assertSame(self::json($workingCopy), $draft->getContent());
        self::assertSame(['en_US' => 'Hello'], $page->getBlockTree()->blocks[0]->fields['text'] ?? null);
    }

    /**
     * @test
     */
    public function it_tells_whether_an_entry_has_a_draft(): void
    {
        $page = self::page('Hello', published: true);

        $this->drafts->findOneByEntry($page)->willReturn(null, $this->draft($page, 'Hello world'));

        self::assertFalse($this->workingCopies()->hasDraft($page));
        self::assertTrue($this->workingCopies()->hasDraft($page));
    }

    /**
     * @test
     */
    public function it_deletes_the_draft_on_discard(): void
    {
        $page = self::page('Hello', published: true);
        $draft = $this->draft($page, 'Hello world');
        $this->drafts->findOneByEntry($page)->willReturn($draft);

        $this->manager->remove($draft)->shouldBeCalledOnce();
        $this->manager->flush()->shouldBeCalledOnce();

        $this->workingCopies()->discard($page);

        self::assertSame(['en_US' => 'Hello'], $page->getBlockTree()->blocks[0]->fields['text'] ?? null);
    }

    /**
     * @test
     */
    public function it_changes_nothing_when_discarding_without_a_draft(): void
    {
        $page = self::page('Hello', published: true);
        $this->drafts->findOneByEntry($page)->willReturn(null);

        $this->manager->remove(Argument::any())->shouldNotBeCalled();
        $this->manager->flush()->shouldNotBeCalled();

        $this->workingCopies()->discard($page);
    }

    /**
     * @test
     */
    public function it_copies_the_working_copy_it_is_given_onto_the_rows_and_deletes_the_draft_without_flushing(): void
    {
        $page = self::page('Hello', published: true);
        $draft = $this->draft($page, 'Hello world', 'About');
        $this->drafts->findOneByEntry($page)->willReturn($draft);
        $workingCopy = $this->workingCopies()->getWorkingCopy($page);
        $workingCopy->getTranslation('en_US')->setTitle('About the shop');

        $this->manager->remove($draft)->shouldBeCalledOnce();
        $this->manager->flush()->shouldNotBeCalled();

        $this->workingCopies()->applyToRows($page, $workingCopy);

        self::assertSame(self::json($workingCopy), self::json($page));
        self::assertSame('About the shop', $page->getTranslation('en_US')->getTitle());
        self::assertSame(['en_US' => 'Hello world'], $page->getBlockTree()->blocks[0]->fields['text'] ?? null);
    }

    /**
     * @test
     */
    public function it_leaves_the_rows_of_a_never_published_entry_which_is_its_own_working_copy(): void
    {
        $page = self::page('Hello');
        $before = self::json($page);
        $this->drafts->findOneByEntry($page)->willReturn(null);

        $this->manager->remove(Argument::any())->shouldNotBeCalled();

        $this->workingCopies()->applyToRows($page, $this->workingCopies()->getWorkingCopy($page));

        self::assertSame($before, self::json($page));
    }

    /**
     * @test
     */
    public function it_holds_the_rows_upgraded_in_a_never_published_entrys_working_copy(): void
    {
        $page = self::page('Hello');
        $page->setBlockTree(self::teaserAtVersion1('Hello'));

        $workingCopy = $this->workingCopies()->getWorkingCopy($page);

        self::assertSame($page, $workingCopy);
        self::assertSame(3, $workingCopy->getBlockTree()->blocks[0]->version);
        self::assertSame(['heading' => ['en_US' => 'Hello']], $workingCopy->getBlockTree()->blocks[0]->fields);
    }

    /**
     * @test
     */
    public function it_holds_the_rows_upgraded_in_a_published_entrys_working_copy_and_leaves_the_rows_as_they_are(): void
    {
        $page = self::page('Hello', published: true);
        $page->setBlockTree(self::teaserAtVersion1('Hello'));
        $rows = $page->getBlockTreeJson();
        $this->drafts->findOneByEntry($page)->willReturn(null);

        $workingCopy = $this->workingCopies()->getWorkingCopy($page);

        self::assertSame(['heading' => ['en_US' => 'Hello']], $workingCopy->getBlockTree()->blocks[0]->fields);
        self::assertSame($rows, $page->getBlockTreeJson());
    }

    /**
     * @test
     */
    public function it_upgrades_a_draft_when_it_reads_it_so_its_working_copy_goes_onto_the_rows_upgraded(): void
    {
        $page = self::page('Hello', published: true);
        $copy = clone $page;
        $copy->setBlockTree(self::teaserAtVersion1('Hello world'));
        $draft = new Draft();
        $draft->setEntry($page);
        $draft->setContent(self::json($copy));
        $this->drafts->findOneByEntry($page)->willReturn($draft);
        $this->manager->remove($draft)->shouldBeCalledOnce();

        $workingCopy = $this->workingCopies()->getWorkingCopy($page);
        self::assertSame(['heading' => ['en_US' => 'Hello world']], $workingCopy->getBlockTree()->blocks[0]->fields);

        $this->workingCopies()->applyToRows($page, $workingCopy);

        self::assertSame(3, $page->getBlockTree()->blocks[0]->version);
        self::assertSame(['heading' => ['en_US' => 'Hello world']], $page->getBlockTree()->blocks[0]->fields);
    }

    private function workingCopies(): WorkingCopyManager
    {
        return new WorkingCopyManager(
            $this->drafts->reveal(),
            new /** @implements FactoryInterface<DraftInterface> */ class() implements FactoryInterface {
                public function createNew(): DraftInterface
                {
                    return new Draft();
                }
            },
            self::normalizer(),
            new EntryDenormalizer(self::registry()),
            $this->manager->reveal(),
            new StoredDataUpgrader(
                new BlockTypeRegistry(['teaser' => Teaser::class], new FieldReader()),
                new LayoutRegistry([], new FieldReader()),
                self::registry(),
                new ServiceLocator([
                    'block_type:teaser:1' => static fn (): RenameField => new RenameField('title', 'headline'),
                    'block_type:teaser:2' => static fn (): RenameField => new RenameField('headline', 'heading'),
                ]),
            ),
        );
    }

    /**
     * A draft of the page whose heading reads $heading, and whose title is $title when given
     */
    private function draft(Page $page, string $heading, ?string $title = null): DraftInterface
    {
        $copy = clone $page;
        $copy->setBlockTree(self::tree($heading));
        if (null !== $title) {
            $copy->getTranslation('en_US')->setTitle($title);
        }

        $draft = new Draft();
        $draft->setEntry($page);
        $draft->setContent(self::json($copy));

        return $draft;
    }

    private static function page(string $heading, bool $published = false): Page
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setTitle('About us');
        $page->setSlug('about-us');
        $page->setBlockTree(self::tree($heading));

        if ($published) {
            $page->publish(new \DateTimeImmutable('2026-11-01 10:00:00', new \DateTimeZone('UTC')));
        }

        return $page;
    }

    private static function tree(string $heading): StoredTree
    {
        $block = new StoredBlock(self::KEY, 'heading', 1);
        $block->setValue('text', 'en_US', $heading);

        return new StoredTree(null, [$block]);
    }

    /**
     * A tree with a Teaser block as version 1 stored it: Teaser is at version 3, and its "heading" was "title" in version 1
     */
    private static function teaserAtVersion1(string $heading): StoredTree
    {
        return new StoredTree(null, [new StoredBlock(self::KEY, 'teaser', 1, fields: ['title' => ['en_US' => $heading]])]);
    }

    private static function json(EntryInterface $entry): string
    {
        return CanonicalJson::encode(self::normalizer()->normalize($entry));
    }

    private static function normalizer(): EntryNormalizer
    {
        return new EntryNormalizer(self::registry());
    }

    private static function registry(): ContentTypeRegistry
    {
        return new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
        ]);
    }
}
