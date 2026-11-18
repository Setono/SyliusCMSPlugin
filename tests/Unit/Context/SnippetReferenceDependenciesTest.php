<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Block\Snippet as SnippetBlock;
use Setono\SyliusCMSPlugin\Context\ContextDependency;
use Setono\SyliusCMSPlugin\Context\ShownSnippetDependencies;
use Setono\SyliusCMSPlugin\Context\SnippetUseDependencies;
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
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Setono\SyliusCMSPlugin\Tree\SnippetUses;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A Snippet block needs what its snippet declares, and holds the snippet's declared context to what the tree it sits in
 * provides. See "Snippets and their context" in docs/spec/slots.md
 */
final class SnippetReferenceDependenciesTest extends TestCase
{
    use ProphecyTrait;

    private const SHOWS_FOOTER = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4a';

    private const SHOWS_NOTE = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4b';

    private const SHOWS_ITSELF = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4c';

    private const SHOWS_DELETED = '0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4d';

    private const DELETED = '0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d01';

    /**
     * @test
     */
    public function it_finds_each_snippet_block_needing_what_the_snippet_it_shows_declares(): void
    {
        $footer = self::snippet('Footer', []);
        $note = self::snippet('Product note', ['product']);
        $promo = self::snippet('Promo', ['product']);
        $promo->setBlockTree(new StoredTree(null, [
            self::shows(self::SHOWS_FOOTER, $footer->getUuid()),
            new StoredBlock('0193a5be-6f1c-7d2e-9b41-6a8f0c2d3e4f', 'heading', 1, children: [self::shows(self::SHOWS_NOTE, $note->getUuid())]),
            self::shows(self::SHOWS_ITSELF, $promo->getUuid()),
            self::shows(self::SHOWS_DELETED, self::DELETED),
        ]));

        $entries = $this->prophesize(EntryRepositoryInterface::class);
        // A snippet that shows itself never renders there, and a deleted one is a broken reference: neither needs anything
        $entries->findByUuids([$footer->getUuid(), $note->getUuid(), self::DELETED])->willReturn([$note, $footer])->shouldBeCalledOnce();

        $snippet = static fn (string $title): TranslatableMessage => new TranslatableMessage('setono_sylius_cms.context.snippet', ['%snippet%' => $title], 'validators');

        // The footer needs nothing
        self::assertEquals([
            new ContextDependency($snippet('Product note'), ['product'], block: self::SHOWS_NOTE),
        ], [...$this->shownSnippets($entries->reveal())->findIn($promo)]);
    }

    /**
     * @test
     */
    public function it_finds_nothing_in_a_tree_that_shows_no_snippet(): void
    {
        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findByUuids(Argument::any())->shouldNotBeCalled();

        self::assertSame([], [...$this->shownSnippets($entries->reveal())->findIn(new Page())]);
    }

    /**
     * @test
     */
    public function it_holds_a_snippet_s_declared_context_to_what_each_entry_that_shows_it_provides(): void
    {
        $note = self::snippet('Product note', ['product']);
        $about = self::page('About us', $note);
        $about->publish(new \DateTimeImmutable());
        $sidebar = self::snippet('Product sidebar', ['product', 'taxon'], $note);
        $sidebarCopy = clone $sidebar;
        $sidebarCopy->setContext(['product']);
        // Shown only in its working copy, which provides nothing more than its rows
        $faq = self::page('FAQ');
        $faq->publish(new \DateTimeImmutable());
        $faqCopy = self::page('FAQ', $note);

        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findReferencing([$note->getUuid()])->willReturn([$about, $sidebar, $faq]);
        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopy($about)->willReturn($about);
        $workingCopies->getWorkingCopy($sidebar)->willReturn($sidebarCopy);
        $workingCopies->getWorkingCopy($faq)->willReturn($faqCopy);

        $usedIn = static fn (string $title): TranslatableMessage => new TranslatableMessage('setono_sylius_cms.context.used_in', ['%entry%' => $title], 'validators');

        self::assertEquals([
            new ContextDependency($usedIn('About us'), provides: ['entry']),
            // Never published, so only its working copy shows the note, with what that declares
            new ContextDependency($usedIn('Product sidebar'), provides: ['product']),
            new ContextDependency($usedIn('FAQ'), provides: ['entry']),
        ], [...$this->snippetUses($entries->reveal(), $workingCopies->reveal())->findIn($note)]);
    }

    /**
     * @test
     */
    public function it_holds_a_snippet_s_declared_context_to_what_both_the_published_content_and_the_working_copy_of_an_entry_that_shows_it_in_both_provide(): void
    {
        $note = self::snippet('Product note', ['product']);
        $sidebar = self::snippet('Product sidebar', ['product', 'taxon'], $note);
        $sidebar->publish(new \DateTimeImmutable());
        $sidebarCopy = clone $sidebar;
        $sidebarCopy->setContext(['product']);

        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findReferencing([$note->getUuid()])->willReturn([$sidebar]);
        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopy($sidebar)->willReturn($sidebarCopy);

        self::assertEquals([
            new ContextDependency(new TranslatableMessage('setono_sylius_cms.context.used_in', ['%entry%' => 'Product sidebar'], 'validators'), provides: ['product']),
        ], [...$this->snippetUses($entries->reveal(), $workingCopies->reveal())->findIn($note)]);
    }

    /**
     * @test
     */
    public function it_finds_no_entries_showing_an_entry_that_is_not_a_snippet(): void
    {
        $entries = $this->prophesize(EntryRepositoryInterface::class);
        $entries->findReferencing(Argument::any())->shouldNotBeCalled();

        self::assertSame([], [...$this->snippetUses($entries->reveal(), $this->prophesize(WorkingCopyProviderInterface::class)->reveal())->findIn(new Page())]);
    }

    /**
     * @param list<string> $context
     */
    private static function snippet(string $title, array $context, ?SnippetInterface $shows = null): SnippetInterface
    {
        $snippet = new Snippet();
        $snippet->setCurrentLocale('en_US');
        $snippet->setTitle($title);
        $snippet->setContext($context);
        if (null !== $shows) {
            $snippet->setBlockTree(new StoredTree(null, [self::shows(self::SHOWS_FOOTER, $shows->getUuid())]));
        }

        return $snippet;
    }

    private static function page(string $title, ?SnippetInterface $shows = null): EntryInterface
    {
        $page = new Page();
        $page->setCurrentLocale('en_US');
        $page->setTitle($title);
        if (null !== $shows) {
            $page->setBlockTree(new StoredTree(null, [self::shows(self::SHOWS_FOOTER, $shows->getUuid())]));
        }

        return $page;
    }

    private static function shows(string $key, string $snippet): StoredBlock
    {
        return new StoredBlock($key, 'snippet', 1, fields: ['snippet' => $snippet]);
    }

    private function shownSnippets(EntryRepositoryInterface $entries): ShownSnippetDependencies
    {
        return new ShownSnippetDependencies(self::referenceFinder(), self::contentTypes(), $entries);
    }

    private function snippetUses(EntryRepositoryInterface $entries, WorkingCopyProviderInterface $workingCopies): SnippetUseDependencies
    {
        return new SnippetUseDependencies(new SnippetUses($entries, $workingCopies, self::referenceFinder(), self::contentTypes()));
    }

    private static function referenceFinder(): ReferenceFinder
    {
        $blockTypes = new BlockTypeRegistry(['heading' => Heading::class, 'snippet' => SnippetBlock::class], new FieldReader());

        return new ReferenceFinder(new TreeValues($blockTypes, new LayoutRegistry([], new FieldReader())), new FieldHandlerLocator(new ServiceLocator([
            TextHandler::class => static fn (): TextHandler => new TextHandler(),
            ChoiceHandler::class => static fn (): ChoiceHandler => new ChoiceHandler(new IdentityTranslator()),
            SnippetHandler::class => static fn (): SnippetHandler => new SnippetHandler(),
        ])));
    }

    private static function contentTypes(): ContentTypeRegistry
    {
        return new ContentTypeRegistry([
            'page' => ['model' => Page::class, 'translation' => PageTranslation::class, 'resource' => 'setono_sylius_cms.page'],
            'snippet' => ['model' => Snippet::class, 'translation' => EntryTranslation::class, 'resource' => 'setono_sylius_cms.snippet'],
        ]);
    }
}
