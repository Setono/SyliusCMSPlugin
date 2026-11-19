<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Transfer\Import\RecordKind;

final class RecordKindTest extends TestCase
{
    /**
     * @test
     */
    public function it_names_its_records_for_the_admin_translation_keys_and_html_attributes(): void
    {
        self::assertSame('entries', RecordKind::Entry->plural());
        self::assertSame('presets', RecordKind::Preset->plural());
        self::assertSame('media_items', RecordKind::MediaItem->plural());
        self::assertSame('media_folders', RecordKind::MediaFolder->plural());
    }

    /**
     * @test
     */
    public function it_names_one_record_and_more_than_one_in_english_for_the_import_command(): void
    {
        self::assertSame(['entry', 'entries'], RecordKind::Entry->nouns());
        self::assertSame(['editor preset', 'editor presets'], RecordKind::Preset->nouns());
        self::assertSame(['media item', 'media items'], RecordKind::MediaItem->nouns());
        self::assertSame(['media folder', 'media folders'], RecordKind::MediaFolder->nouns());
    }
}
