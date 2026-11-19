<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Tree;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\Snippet as SnippetBlock;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\SnippetHandler;
use Setono\SyliusCMSPlugin\Field\TextHandler;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistry;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture\Lookbook;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Tree\SnippetUse;
use Setono\SyliusCMSPlugin\Tree\SnippetUses;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;

final class SnippetUsesTest extends TestCase
{
    use ProphecyTrait;

    private const REFERENCE = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const HEADING = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    /**
     * @test
     */
    public function it_finds_the_entries_whose_rows_or_working_copy_show_the_snippet_through_a_snippet_field(): void
    {
        $footer = new Snippet();

        $published = self::page(self::shows($footer));
        $published->publish(new \DateTimeImmutable());
        $drafted = self::page(self::heading());
        $drafted->publish(new \DateTimeImmutable());
        $removedInDraft = self::page(self::shows($footer));
        $removedInDraft->publish(new \DateTimeImmutable());
        $neverPublished = new Snippet();
        $neverPublished->setBlockTree(new StoredTree(null, [self::shows($footer)]));
        // The UUID is in its draft, but not in a Snippet field
        $mentions = self::page(self::heading());

        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findReferencing([$footer->getUuid()])->willReturn([$published, $drafted, $removedInDraft, $neverPublished, $mentions, $footer]);

        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopy($published)->willReturn($published);
        $workingCopies->getWorkingCopy($drafted)->willReturn($draftedCopy = self::page(self::heading(), self::shows($footer)));
        $workingCopies->getWorkingCopy($removedInDraft)->willReturn(self::page(self::heading()));
        $workingCopies->getWorkingCopy($neverPublished)->willReturn($neverPublished);
        $workingCopies->getWorkingCopy($mentions)->willReturn(self::page(self::heading()));
        $workingCopies->getWorkingCopy($footer)->willReturn($footer);

        self::assertEquals([
            new SnippetUse($published, true, $published),
            new SnippetUse($drafted, false, $draftedCopy),
            new SnippetUse($removedInDraft, true, null),
            new SnippetUse($neverPublished, false, $neverPublished),
        ], $this->uses($entries->reveal(), $workingCopies->reveal())->of($footer));
    }

    /**
     * A content type's own Snippet field shows it as a Snippet block does
     *
     * @test
     */
    public function it_finds_an_entry_whose_own_snippet_field_shows_the_snippet(): void
    {
        $footer = new Snippet();
        $lookbook = new Lookbook();
        $lookbook->banner = $footer->getUuid();
        $lookbook->publish(new \DateTimeImmutable());

        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findReferencing([$footer->getUuid()])->willReturn([$lookbook]);
        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopy($lookbook)->willReturn($lookbook);

        self::assertEquals([new SnippetUse($lookbook, true, $lookbook)], $this->uses($entries->reveal(), $workingCopies->reveal())->of($footer));
    }

    /**
     * @test
     */
    public function it_leaves_out_a_snippet_that_shows_itself(): void
    {
        $footer = new Snippet();
        $footer->setBlockTree(new StoredTree(null, [self::shows($footer)]));

        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findReferencing([$footer->getUuid()])->willReturn([$footer]);

        self::assertSame([], $this->uses($entries->reveal(), $this->prophesize(WorkingCopyProviderInterface::class)->reveal())->of($footer));
    }

    private function uses(EntryRepositoryInterface $entries, WorkingCopyProviderInterface $workingCopies): SnippetUses
    {
        $blockTypes = new BlockTypeRegistry(['heading' => Heading::class, 'snippet' => SnippetBlock::class], new FieldReader());
        $handlers = new FieldHandlerLocator(new ServiceLocator([
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler(new IdentityTranslator()),
            SnippetHandler::class => static fn (): SnippetHandler => new SnippetHandler(),
        ]));

        return new SnippetUses(
            $entries,
            $workingCopies,
            new ReferenceFinder(new TreeValues($blockTypes, new LayoutRegistry([], new FieldReader())), $handlers),
            new ContentTypeRegistry([
                'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
                'snippet' => ['model' => Snippet::class, 'translation' => EntryTranslation::class, 'resource' => 'setono_sylius_cms.snippet'],
                'lookbook' => ['model' => Lookbook::class, 'translation' => EntryTranslation::class, 'resource' => 'app.lookbook'],
            ]),
        );
    }

    private static function page(StoredBlock ...$blocks): EntryInterface
    {
        $page = new Page();
        $page->setBlockTree(new StoredTree(null, array_values($blocks)));

        return $page;
    }

    private static function shows(SnippetInterface $snippet): StoredBlock
    {
        return new StoredBlock(self::REFERENCE, 'snippet', 1, fields: ['snippet' => $snippet->getUuid()]);
    }

    private static function heading(): StoredBlock
    {
        return new StoredBlock(self::HEADING, 'heading', 1, fields: ['text' => ['en_US' => 'Hello']]);
    }
}
