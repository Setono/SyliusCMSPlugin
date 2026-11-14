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
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Repository\DraftRepositoryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Sylius\Resource\Factory\FactoryInterface;

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
    public function a_never_published_entry_is_its_own_working_copy_and_saving_it_writes_the_rows(): void
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
    public function a_published_entry_without_a_draft_has_a_copy_of_its_rows_as_its_working_copy(): void
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
    public function the_first_save_after_a_publish_creates_the_draft_and_leaves_the_rows(): void
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
    public function a_published_entry_with_a_draft_has_the_draft_as_its_working_copy_and_saving_updates_it(): void
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
    public function discarding_deletes_the_draft(): void
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
    public function discarding_without_a_draft_changes_nothing(): void
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
    public function applying_the_draft_copies_it_onto_the_rows_and_deletes_it_without_flushing(): void
    {
        $page = self::page('Hello', published: true);
        $draft = $this->draft($page, 'Hello world', 'About');
        $this->drafts->findOneByEntry($page)->willReturn($draft);

        $this->manager->remove($draft)->shouldBeCalledOnce();
        $this->manager->flush()->shouldNotBeCalled();

        $this->workingCopies()->applyDraft($page);

        self::assertSame($draft->getContent(), self::json($page));
        self::assertSame('About', $page->getTranslation('en_US')->getTitle());
    }

    /**
     * @test
     */
    public function applying_without_a_draft_leaves_the_rows(): void
    {
        $page = self::page('Hello');
        $before = self::json($page);
        $this->drafts->findOneByEntry($page)->willReturn(null);

        $this->manager->remove(Argument::any())->shouldNotBeCalled();

        $this->workingCopies()->applyDraft($page);

        self::assertSame($before, self::json($page));
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
