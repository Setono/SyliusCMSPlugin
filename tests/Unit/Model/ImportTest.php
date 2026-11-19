<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Import;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportState;
use Symfony\Component\Uid\Uuid;

final class ImportTest extends TestCase
{
    /**
     * @test
     */
    public function it_is_an_uploaded_zip_the_worker_checks_first(): void
    {
        $import = self::uploaded();

        self::assertNull($import->getId());
        self::assertTrue(Uuid::isValid($import->getUuid()));
        self::assertSame('cms-export.zip', $import->getFilename());
        self::assertSame('imports/abc.zip', $import->getPath());
        self::assertSame('2026-11-20T08:00:00+00:00', $import->getCreatedAt()->format(\DATE_ATOM));
        self::assertSame(ImportState::Checking, $import->getState());
        self::assertTrue($import->isWaiting());
        self::assertNull($import->getStartedAt());
        self::assertNull($import->getDryRun());
        self::assertNull($import->getResult());
        self::assertNull($import->getFinishedAt());
        self::assertFalse($import->isOverwrite());
        self::assertFalse($import->isPublish());
    }

    /**
     * @test
     */
    public function it_waits_for_the_admin_user_once_its_dry_run_would_apply_and_then_for_the_worker_again(): void
    {
        $import = self::uploaded();
        $import->start(new \DateTimeImmutable('2026-11-20 09:01:00', new \DateTimeZone('Europe/Copenhagen')));
        self::assertSame('2026-11-20T08:01:00+00:00', $import->getStartedAt()?->format(\DATE_ATOM));

        $import->check(['status' => 'would_apply']);

        self::assertSame(ImportState::Checked, $import->getState());
        self::assertFalse($import->isWaiting());
        self::assertSame(['status' => 'would_apply'], $import->getDryRun());
        self::assertNull($import->getStartedAt());

        $import->confirm(true, true);

        self::assertSame(ImportState::Importing, $import->getState());
        self::assertTrue($import->isWaiting());
        self::assertTrue($import->isOverwrite());
        self::assertTrue($import->isPublish());
    }

    /**
     * @test
     */
    public function it_records_its_result_and_lets_go_of_the_zip_when_it_finishes(): void
    {
        $import = self::uploaded();
        $import->check([]);
        $import->confirm(false, false);

        $import->finish(['status' => 'refused'], new \DateTimeImmutable('2026-11-20 08:05:00'));

        self::assertSame(ImportState::Finished, $import->getState());
        self::assertFalse($import->isWaiting());
        self::assertSame(['status' => 'refused'], $import->getResult());
        self::assertNull($import->getPath());
        self::assertSame('2026-11-20T08:05:00+00:00', $import->getFinishedAt()?->format(\DATE_ATOM));
    }

    /**
     * @test
     */
    public function it_lets_go_of_the_zip_when_it_is_abandoned(): void
    {
        $import = self::uploaded();

        $import->abandon(new \DateTimeImmutable('2026-11-21 08:00:00'));

        self::assertSame(ImportState::Abandoned, $import->getState());
        self::assertFalse($import->isWaiting());
        self::assertNull($import->getPath());
        self::assertNull($import->getResult());
        self::assertSame('2026-11-21T08:00:00+00:00', $import->getFinishedAt()?->format(\DATE_ATOM));
    }

    /**
     * @test
     */
    public function it_records_why_it_failed_and_lets_go_of_the_zip_when_the_worker_gives_up_on_it(): void
    {
        $import = self::uploaded();
        $import->check([]);
        $import->confirm(false, false);

        $import->fail('The database went away', new \DateTimeImmutable('2026-11-21 08:00:00'));

        self::assertSame(ImportState::Failed, $import->getState());
        self::assertFalse($import->isWaiting());
        self::assertSame('The database went away', $import->getFailure());
        self::assertNull($import->getPath());
        self::assertSame('2026-11-21T08:00:00+00:00', $import->getFinishedAt()?->format(\DATE_ATOM));
    }

    /**
     * @test
     */
    public function it_fails_only_while_the_worker_checks_or_imports_it(): void
    {
        $import = self::uploaded();
        $import->check([]);

        $this->expectException(\LogicException::class);

        $import->fail('The database went away', new \DateTimeImmutable());
    }

    /**
     * @test
     */
    public function it_is_confirmed_only_once_its_dry_run_would_apply(): void
    {
        $import = self::uploaded();

        $this->expectException(\LogicException::class);

        $import->confirm(false, false);
    }

    /**
     * @test
     */
    public function it_is_not_abandoned_once_it_is_confirmed(): void
    {
        $import = self::uploaded();
        $import->check([]);
        $import->confirm(false, false);

        $this->expectException(\LogicException::class);

        $import->abandon(new \DateTimeImmutable());
    }

    /**
     * @test
     */
    public function it_is_checked_only_while_it_is_checked(): void
    {
        $import = self::uploaded();
        $import->abandon(new \DateTimeImmutable());

        $this->expectException(\LogicException::class);

        $import->check([]);
    }

    private static function uploaded(): Import
    {
        $import = new Import();
        $import->setFilename('cms-export.zip');
        $import->setPath('imports/abc.zip');
        $import->setCreatedAt(new \DateTimeImmutable('2026-11-20 09:00:00', new \DateTimeZone('Europe/Copenhagen')));

        return $import;
    }
}
