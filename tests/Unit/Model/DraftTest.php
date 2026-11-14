<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Model\Draft;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\Page;

final class DraftTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_holds_an_entry_in_the_entry_format(): void
    {
        $page = new Page();
        $draft = new Draft();
        self::assertNull($draft->getId());
        self::assertSame('', $draft->getContent());

        $draft->setEntry($page);
        $draft->setContent('{"uuid":"0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e"}');

        self::assertSame($page, $draft->getEntry());
        self::assertSame('{"uuid":"0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e"}', $draft->getContent());
    }

    /**
     * @test
     */
    public function it_belongs_to_an_entry_of_a_content_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Draft())->setEntry($this->prophesize(EntryInterface::class)->reveal());
    }
}
