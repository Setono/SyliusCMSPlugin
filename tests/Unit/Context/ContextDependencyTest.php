<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Context\ContextDependency;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Something that depends on the host-page context. See "Snippets and their context" in docs/spec/slots.md
 */
final class ContextDependencyTest extends TestCase
{
    /**
     * Publish refuses a source such as In this category in an entry of another content type, and a preset holding one
     * isn't offered there. A snippet's entry is the host page's, of any content type
     *
     * @test
     */
    public function it_fits_an_entry_of_a_content_type_it_names_any_entry_when_it_names_none_and_a_snippets(): void
    {
        $inThisCategory = new ContextDependency(new TranslatableMessage('setono_sylius_cms.source.entries_in_this_category'), ['entry'], entryContentTypes: ['blog_category', 'faq_category']);
        self::assertTrue($inThisCategory->fitsEntryOf('blog_category'));
        self::assertFalse($inThisCategory->fitsEntryOf('blog_post'));
        self::assertTrue($inThisCategory->fitsEntryOf(null), 'A snippet\'s entry can be of any content type');

        $nowhere = new ContextDependency(new TranslatableMessage('setono_sylius_cms.source.related_entries'), ['entry'], entryContentTypes: []);
        self::assertFalse($nowhere->fitsEntryOf('page'));

        $anywhere = new ContextDependency(new TranslatableMessage('setono_sylius_cms.source.bestsellers'), ['entry']);
        self::assertTrue($anywhere->fitsEntryOf('page'));
        self::assertTrue($anywhere->fitsEntryOf(null));
    }
}
