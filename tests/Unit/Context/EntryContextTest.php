<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Context\EntryContext;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture\Recipe;

/**
 * An entry the CMS renders provides itself, as entry, to its own tree, and a snippet the context it declares.
 * See "Context keys" and "Snippets and their context" in docs/spec/slots.md
 */
final class EntryContextTest extends TestCase
{
    /**
     * @test
     */
    public function it_gives_an_entry_s_tree_the_entry_itself(): void
    {
        $page = new Page();

        self::assertSame(['entry'], EntryContext::keys($page));
        self::assertSame(['entry' => $page], EntryContext::objects($page));
    }

    /**
     * @test
     */
    public function it_gives_a_snippet_s_tree_the_context_it_declares_which_its_slot_provides(): void
    {
        $snippet = new Snippet();
        $snippet->setContext(['product']);

        self::assertSame(['product'], EntryContext::keys($snippet));
        self::assertSame([], EntryContext::objects($snippet), 'The slot it shows in provides the objects');
    }

    /**
     * @test
     */
    public function it_tells_the_keys_an_entry_of_a_content_type_provides_or_null_for_a_snippet_which_declares_its_own(): void
    {
        self::assertSame(['entry'], EntryContext::keysOf(new ContentTypeMetadata('page', 'Page', 1, '', null, Page::class, PageTranslation::class, 'setono_sylius_cms.page')));
        self::assertSame(['entry'], EntryContext::keysOf(new ContentTypeMetadata('recipe', 'Recipe', 1, null, null, Recipe::class, null, 'app.recipe')));
        self::assertNull(EntryContext::keysOf(new ContentTypeMetadata('snippet', 'Snippet', 1, null, null, Snippet::class, null, 'setono_sylius_cms.snippet')));
    }
}
