<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Publishing;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Model\Page;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProvider;

final class WorkingCopyProviderTest extends TestCase
{
    /**
     * @test
     */
    public function until_drafts_arrive_the_working_copy_is_the_entrys_rows(): void
    {
        $page = new Page();

        self::assertSame($page, (new WorkingCopyProvider())->getWorkingCopy($page));
    }
}
