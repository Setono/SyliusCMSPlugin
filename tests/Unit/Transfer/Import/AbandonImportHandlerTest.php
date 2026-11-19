<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Transfer\Import;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Model\Import;
use Setono\SyliusCMSPlugin\Repository\ImportRepositoryInterface;
use Setono\SyliusCMSPlugin\Transfer\Import\AbandonImport;
use Setono\SyliusCMSPlugin\Transfer\Import\AbandonImportHandler;
use Setono\SyliusCMSPlugin\Transfer\Import\ImportQueueInterface;

final class AbandonImportHandlerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_abandons_an_import_no_one_confirmed_whether_its_dry_run_ran_or_not(): void
    {
        $checking = new Import();
        $checked = new Import();
        $checked->check([]);
        $imports = $this->prophesize(ImportRepositoryInterface::class);
        $imports->findOneByUuid($checking->getUuid())->willReturn($checking);
        $imports->findOneByUuid($checked->getUuid())->willReturn($checked);
        $queue = $this->prophesize(ImportQueueInterface::class);
        $queue->abandon($checking)->shouldBeCalledOnce();
        $queue->abandon($checked)->shouldBeCalledOnce();
        $handler = new AbandonImportHandler($imports->reveal(), $queue->reveal());

        $handler(new AbandonImport($checking->getUuid()));
        $handler(new AbandonImport($checked->getUuid()));
    }

    /**
     * @test
     */
    public function it_leaves_an_import_that_was_confirmed_or_is_done_or_gone_as_it_is(): void
    {
        $importing = new Import();
        $importing->check([]);
        $importing->confirm(false, false);
        $abandoned = new Import();
        $abandoned->abandon(new \DateTimeImmutable());
        $imports = $this->prophesize(ImportRepositoryInterface::class);
        $imports->findOneByUuid($importing->getUuid())->willReturn($importing);
        $imports->findOneByUuid($abandoned->getUuid())->willReturn($abandoned);
        $imports->findOneByUuid('0193a5be-0000-7000-8000-00000000dead')->willReturn(null);
        $queue = $this->prophesize(ImportQueueInterface::class);
        $queue->abandon(Argument::any())->shouldNotBeCalled();
        $handler = new AbandonImportHandler($imports->reveal(), $queue->reveal());

        $handler(new AbandonImport($importing->getUuid()));
        $handler(new AbandonImport($abandoned->getUuid()));
        $handler(new AbandonImport('0193a5be-0000-7000-8000-00000000dead'));
    }
}
