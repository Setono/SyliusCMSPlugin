<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\EntryTranslation;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Registry\AttributeReader;

final class SnippetTest extends TestCase
{
    /**
     * @test
     */
    public function it_is_the_built_in_content_type_snippet_with_no_url_and_any_block_type_at_the_top_level(): void
    {
        $contentType = AttributeReader::contentType(Snippet::class);

        self::assertNotNull($contentType);
        self::assertSame('snippet', $contentType->code);
        self::assertSame('setono_sylius_cms.content_type.snippet', $contentType->label);
        self::assertNull($contentType->prefix);
        self::assertCount(1, $contentType->blocks ?? []);
        self::assertSame('*', ($contentType->blocks ?? [])[0]->type);
    }

    /**
     * @test
     */
    public function it_needs_no_host_page_context_until_it_declares_some(): void
    {
        $snippet = new Snippet();

        self::assertSame([], $snippet->getContext());

        $snippet->setContext(['product', 'taxon', 'product']);

        self::assertSame(['product', 'taxon'], $snippet->getContext());
    }

    /**
     * @test
     */
    public function its_translations_are_entry_translations(): void
    {
        $snippet = new Snippet();
        $snippet->setCurrentLocale('en_US');
        $snippet->setFallbackLocale('en_US');

        self::assertSame(EntryTranslation::class, $snippet->getTranslation()::class);
    }

    /**
     * @test
     */
    public function it_has_no_layout(): void
    {
        self::assertNull((new Snippet())->getBlockTree()->layout);
    }
}
