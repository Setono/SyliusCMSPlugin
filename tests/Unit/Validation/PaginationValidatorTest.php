<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Model\PageTranslation;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Validation\PaginationValidator;
use Setono\SyliusCMSPlugin\Validation\ValidationError;

/**
 * The paginated Entries block's publish rules: only an entry with its own URL paginates, through one Entries block in its
 * own tree, filled by a source. See "Pagination" in docs/spec/collections.md
 */
final class PaginationValidatorTest extends TestCase
{
    private const FIRST = '0193a5be-0000-7000-8000-0000000000e1';

    private const SECOND = '0193a5be-0000-7000-8000-0000000000e2';

    private const GROUP = '0193a5be-0000-7000-8000-0000000000e3';

    private const SOURCE = ['$source' => 'entries_from_collection', '$params' => ['contentType' => 'blog_post'], '$limit' => 9];

    /**
     * @test
     */
    public function it_accepts_one_paginated_entries_block_filled_by_a_source_in_an_entry_with_its_own_url(): void
    {
        self::assertSame([], self::validator()->validate(self::tree(self::entries(self::FIRST), self::entries(self::SECOND, paginate: false)), self::page()));
    }

    /**
     * @test
     */
    public function it_refuses_a_second_paginated_entries_block_however_deep_it_is(): void
    {
        $errors = self::validator()->validate(self::tree(new StoredBlock(self::GROUP, 'group', 1, children: [self::entries(self::FIRST)]), self::entries(self::SECOND)), self::page());

        self::assertEquals([new ValidationError(self::SECOND, 'paginate', null, 'Only one list on a page can be split into pages. Turn off "Split into pages" here, or on the other list.')], $errors);
    }

    /**
     * A snippet in a slot on a taxon page would clash with Sylius's own ?page=N
     *
     * @test
     */
    public function it_refuses_a_paginated_entries_block_in_a_snippet(): void
    {
        $errors = self::validator()->validate(self::tree(self::entries(self::FIRST), self::entries(self::SECOND)), self::snippet());

        self::assertEquals([
            new ValidationError(self::FIRST, 'paginate', null, 'Only an entry with its own URL can split a list into pages. Turn off "Split into pages".'),
            new ValidationError(self::SECOND, 'paginate', null, 'Only an entry with its own URL can split a list into pages. Turn off "Split into pages".'),
        ], $errors);
    }

    /**
     * Its page size is the source's $limit, and a hand-picked list has none
     *
     * @test
     */
    public function it_refuses_a_paginated_entries_block_whose_entries_are_hand_picked(): void
    {
        $errors = self::validator()->validate(self::tree(self::entries(self::FIRST, ['0193a5be-0000-7000-8000-000000000001'])), self::page());

        self::assertEquals([new ValidationError(self::FIRST, 'paginate', null, 'A list can only be split into pages when a source fills it. Pick a source under "Filled by", or turn off "Split into pages".')], $errors);
    }

    /**
     * @test
     */
    public function it_ignores_entries_blocks_that_do_not_paginate_and_other_blocks(): void
    {
        $tree = self::tree(self::entries(self::FIRST, [], paginate: false), new StoredBlock(self::GROUP, 'group', 1, fields: ['paginate' => true]));

        self::assertSame([], self::validator()->validate($tree, self::page()));
        self::assertSame([], self::validator()->validate($tree, self::snippet()));
    }

    private static function validator(): PaginationValidator
    {
        return new PaginationValidator(BlockTreeValidatorTest::translator());
    }

    private static function tree(StoredBlock ...$blocks): StoredTree
    {
        return new StoredTree(null, array_values($blocks));
    }

    /**
     * @param array<array-key, mixed> $entries the Entries field's stored value
     */
    private static function entries(string $key, array $entries = self::SOURCE, bool $paginate = true): StoredBlock
    {
        return new StoredBlock($key, 'entries', 1, fields: ['entries' => $entries, 'display' => 'grid', 'paginate' => $paginate]);
    }

    private static function page(): ContentTypeMetadata
    {
        return new ContentTypeMetadata('page', 'app.label', 1, '', null, Page::class, PageTranslation::class, 'setono_sylius_cms.page');
    }

    private static function snippet(): ContentTypeMetadata
    {
        return new ContentTypeMetadata('snippet', 'app.label', 1, null, null, Snippet::class, null, 'setono_sylius_cms.snippet');
    }
}
