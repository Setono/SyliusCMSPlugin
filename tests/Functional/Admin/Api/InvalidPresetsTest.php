<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Admin\Api;

use Setono\SyliusCMSPlugin\Tests\Functional\Fixture\BrokenPresetsKernel;

/**
 * Presets with invalid blocks are marked in the Presets tab and say why. Inserting one leaves out the blocks of removed
 * types with a notice, and refuses one with a block newer than the code's. A disabled code preset isn't offered. See
 * "Presets with invalid blocks" in docs/spec/layouts-and-presets.md
 */
final class InvalidPresetsTest extends BuilderApiTestCase
{
    protected static function getKernelClass(): string
    {
        return BrokenPresetsKernel::class;
    }

    /**
     * @test
     */
    public function it_marks_presets_with_invalid_blocks_saying_why_and_leaves_out_disabled_and_unreadable_ones(): void
    {
        $client = self::signedIn();
        $page = self::createPage('About us', 'about-us');

        $presets = self::presetsAt($client, sprintf('/admin/cms/api/presets?entry=%s', $page->getUuid()));

        // The fixture kernel's directory comes first, as a bundle's prepended one does
        self::assertSame(['future', 'misfit', 'retired', 'column_pair', 'hero'], array_keys($presets));

        self::assertSame(['It has blocks of the type "gallery", which the code no longer has, so inserting it leaves them out.'], $presets['retired']['issues']);
        // What's left of it can be inserted
        self::assertTrue($presets['retired']['fits']);
        self::assertSame(['heading'], array_column($presets['retired']['blocks'], 'type'));

        $refused = '"heading" in the preset was saved with version 9, but this store has version 1, so the preset can\'t be inserted.';
        self::assertSame([$refused], $presets['future']['issues']);
        self::assertSame([$refused], $presets['future']['reasons']);
        self::assertFalse($presets['future']['fits']);

        self::assertSame([
            'Heading isn\'t allowed in Columns.',
            'Columns needs at least 2 Column blocks.',
            'Level in the Heading block holds a value that doesn\'t fit its field type, so it counts as empty.',
        ], $presets['misfit']['issues']);
        // Other problems are inserted and flagged like any invalid block
        self::assertTrue($presets['misfit']['fits']);
        self::assertSame([], $presets['hero']['issues']);
    }

    /**
     * @test
     */
    public function it_leaves_out_the_blocks_of_removed_types_on_insert_with_a_notice(): void
    {
        $client = self::signedIn();
        $page = self::createPage('About us', 'about-us');

        $response = self::json($client, 'POST', '/admin/cms/api/presets/retired/insert', ['entry' => $page->getUuid()]);

        self::assertResponseIsSuccessful();
        self::assertIsArray($response);
        self::assertSame(['Blocks of the type "gallery", which the code no longer has, were left out.'], $response['notices']);
        self::assertIsArray($response['blocks']);
        self::assertSame(['heading'], array_column($response['blocks'], 'type'));
    }

    /**
     * @test
     */
    public function it_refuses_to_insert_a_preset_with_a_block_newer_than_the_code_s_and_a_disabled_one(): void
    {
        $client = self::signedIn();
        $page = self::createPage('About us', 'about-us');

        $response = self::json($client, 'POST', '/admin/cms/api/presets/future/insert', ['entry' => $page->getUuid()]);

        self::assertResponseStatusCodeSame(422);
        self::assertIsArray($response);
        self::assertSame(['"heading" in the preset was saved with version 9, but this store has version 1, so the preset can\'t be inserted.'], $response['notices']);

        self::json($client, 'POST', '/admin/cms/api/presets/product_spotlight/insert', ['entry' => $page->getUuid()]);
        self::assertResponseStatusCodeSame(404);
    }
}
