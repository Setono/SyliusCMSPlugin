<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preset;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Preset\PresetIssueKind;

/**
 * Which issues make the create dialog refuse a starting point: blocks of removed types, and what's newer than the code.
 * See "Presets with invalid blocks" in docs/spec/layouts-and-presets.md
 */
final class PresetIssueKindTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider provideKinds
     */
    public function it_disables_a_starting_point_only_for_blocks_of_removed_types_and_what_is_newer_than_the_code(PresetIssueKind $kind, bool $disables): void
    {
        self::assertSame($disables, $kind->disablesStartingPoint());
    }

    /**
     * @return iterable<string, array{PresetIssueKind, bool}>
     */
    public static function provideKinds(): iterable
    {
        yield 'a removed block type' => [PresetIssueKind::RemovedBlockType, true];
        yield 'a newer version' => [PresetIssueKind::NewerVersion, true];
        yield 'a disabled block type, whose blocks are left out' => [PresetIssueKind::DisabledBlockType, false];
        yield 'nesting, flagged once created' => [PresetIssueKind::Nesting, false];
        yield 'a misfit value, flagged once created' => [PresetIssueKind::Misfit, false];
        yield 'a removed layout, replaced by the default' => [PresetIssueKind::StartingPoint, false];
    }
}
