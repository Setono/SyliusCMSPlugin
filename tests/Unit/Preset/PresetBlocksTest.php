<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preset;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Block\Column;
use Setono\SyliusCMSPlugin\Block\Columns;
use Setono\SyliusCMSPlugin\Block\EmbedCode;
use Setono\SyliusCMSPlugin\Block\Heading;
use Setono\SyliusCMSPlugin\Preset\PresetBlocks;
use Setono\SyliusCMSPlugin\Registry\BlockTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistry;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;

/**
 * A preset's blocks as inserting reads them. See "Presets with invalid blocks" in docs/spec/layouts-and-presets.md
 */
final class PresetBlocksTest extends TestCase
{
    /**
     * @test
     */
    public function it_walks_the_blocks_parents_first_with_their_types_not_into_a_block_of_a_type_the_code_no_longer_has(): void
    {
        self::assertSame([
            'columns:columns',
            'column:column',
            'gallery:removed',
            'embed_code:embed_code',
            'heading:heading',
            'heading:heading',
        ], self::walked(self::blocks(), intoDisabled: true));
    }

    /**
     * Inserting leaves out a block of a disabled type with what's inside it, so it doesn't look inside either
     *
     * @test
     */
    public function it_walks_into_a_block_of_a_disabled_type_only_when_asked(): void
    {
        self::assertSame([
            'columns:columns',
            'column:column',
            'gallery:removed',
            'embed_code:embed_code',
            'heading:heading',
        ], self::walked(self::blocks(), intoDisabled: false));
    }

    /**
     * @param list<StoredBlock> $blocks
     *
     * @return list<string> each block's type, and the code of the type it was walked with, or "removed"
     */
    private static function walked(array $blocks, bool $intoDisabled): array
    {
        $blockTypes = new BlockTypeRegistry([
            'heading' => Heading::class,
            'columns' => Columns::class,
            'column' => Column::class,
            'embed_code' => EmbedCode::class,
        ], new FieldReader(), ['embed_code']);

        $walked = [];
        foreach (PresetBlocks::walk($blocks, $blockTypes, $intoDisabled) as [$block, $blockType]) {
            $walked[] = sprintf('%s:%s', $block->type, $blockType instanceof BlockTypeMetadata ? $blockType->code : 'removed');
        }

        return $walked;
    }

    /**
     * @return list<StoredBlock>
     */
    private static function blocks(): array
    {
        return [
            new StoredBlock('0193a5be-0000-7000-8000-000000000001', 'columns', 1, children: [
                new StoredBlock('0193a5be-0000-7000-8000-000000000002', 'column', 1, children: [
                    new StoredBlock('0193a5be-0000-7000-8000-000000000003', 'gallery', 1, children: [new StoredBlock('0193a5be-0000-7000-8000-000000000004', 'heading', 1)]),
                ]),
            ]),
            new StoredBlock('0193a5be-0000-7000-8000-000000000005', 'embed_code', 1, children: [new StoredBlock('0193a5be-0000-7000-8000-000000000006', 'heading', 1)]),
            new StoredBlock('0193a5be-0000-7000-8000-000000000007', 'heading', 1),
        ];
    }
}
