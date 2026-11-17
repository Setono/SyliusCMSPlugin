<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Validation;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Setono\SyliusCMSPlugin\Validation\EmbedCodeValidator;
use Setono\SyliusCMSPlugin\Validation\ValidationError;

/**
 * The publish rule of the Embed code block: while setono_sylius_cms.embed_code is off, an embed_code block whose key
 * isn't already in the entry's published tree is refused, however it got into the draft.
 * See "Validation" in docs/spec/publishing.md and ADR 0018
 */
final class EmbedCodeValidatorTest extends TestCase
{
    private const PUBLISHED = '0193a5be-0000-7000-8000-0000000000e1';

    private const NEW = '0193a5be-0000-7000-8000-0000000000e2';

    private const GROUP = '0193a5be-0000-7000-8000-0000000000e3';

    private const MESSAGE = 'Embed code is switched off in this shop, so a new Embed code block can\'t be published. Remove it.';

    /**
     * @test
     */
    public function it_refuses_an_embed_code_block_whose_key_is_not_in_the_published_tree_while_embed_code_is_off(): void
    {
        $errors = self::validator(false)->validate(
            self::tree(self::embedCode(self::PUBLISHED), self::embedCode(self::NEW)),
            self::tree(self::embedCode(self::PUBLISHED)),
        );

        self::assertEquals([new ValidationError(self::NEW, null, null, self::MESSAGE)], $errors);
    }

    /**
     * @test
     */
    public function it_accepts_an_embed_code_block_whose_key_is_in_the_published_tree_while_embed_code_is_off_even_with_its_code_changed(): void
    {
        self::assertSame([], self::validator(false)->validate(
            self::tree(self::embedCode(self::PUBLISHED, '<p>Changed</p>')),
            self::tree(self::embedCode(self::PUBLISHED, '<p>Published</p>')),
        ));
    }

    /**
     * @test
     */
    public function it_accepts_an_embed_code_block_moved_into_another_block_since_it_was_published(): void
    {
        self::assertSame([], self::validator(false)->validate(
            self::tree(new StoredBlock(self::GROUP, 'group', 1, children: [self::embedCode(self::PUBLISHED)])),
            self::tree(new StoredBlock(self::GROUP, 'group', 1), self::embedCode(self::PUBLISHED)),
        ));
    }

    /**
     * @test
     */
    public function it_refuses_a_new_embed_code_block_inside_another_block(): void
    {
        $errors = self::validator(false)->validate(
            self::tree(new StoredBlock(self::GROUP, 'group', 1, children: [self::embedCode(self::NEW)])),
            self::tree(new StoredBlock(self::GROUP, 'group', 1)),
        );

        self::assertEquals([new ValidationError(self::NEW, null, null, self::MESSAGE)], $errors);
    }

    /**
     * @test
     */
    public function it_refuses_an_embed_code_block_whose_key_is_published_on_a_block_of_another_type(): void
    {
        $errors = self::validator(false)->validate(
            self::tree(self::embedCode(self::GROUP)),
            self::tree(new StoredBlock(self::GROUP, 'group', 1)),
        );

        self::assertEquals([new ValidationError(self::GROUP, null, null, self::MESSAGE)], $errors);
    }

    /**
     * @test
     */
    public function it_refuses_every_embed_code_block_of_an_entry_with_nothing_published_while_embed_code_is_off(): void
    {
        $errors = self::validator(false)->validate(self::tree(self::embedCode(self::PUBLISHED), self::embedCode(self::NEW)), self::tree());

        self::assertEquals([
            new ValidationError(self::PUBLISHED, null, null, self::MESSAGE),
            new ValidationError(self::NEW, null, null, self::MESSAGE),
        ], $errors);
    }

    /**
     * @test
     */
    public function it_accepts_new_embed_code_blocks_while_embed_code_is_on(): void
    {
        self::assertSame([], self::validator(true)->validate(
            self::tree(self::embedCode(self::NEW), new StoredBlock(self::GROUP, 'group', 1, children: [self::embedCode(self::PUBLISHED)])),
            self::tree(),
        ));
    }

    private static function validator(bool $enabled): EmbedCodeValidator
    {
        return new EmbedCodeValidator($enabled, BlockTreeValidatorTest::translator());
    }

    private static function tree(StoredBlock ...$blocks): StoredTree
    {
        return new StoredTree(null, array_values($blocks));
    }

    private static function embedCode(string $key, string $code = '<script>window.reviews = true;</script>'): StoredBlock
    {
        return new StoredBlock($key, 'embed_code', 1, fields: ['code' => $code]);
    }
}
