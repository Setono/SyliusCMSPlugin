<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportAction;

/**
 * See "What an import does" in docs/spec/transfer.md
 */
final class ImportActionTest extends TestCase
{
    /**
     * @test
     */
    public function it_writes_a_record_it_creates_or_updates_only(): void
    {
        self::assertTrue(ImportAction::Create->writes());
        self::assertTrue(ImportAction::Update->writes());
        self::assertFalse(ImportAction::Unchanged->writes());
        self::assertFalse(ImportAction::LeaveAlone->writes());
    }
}
