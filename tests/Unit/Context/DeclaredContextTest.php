<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusCMSPlugin\Context\DeclaredContext;
use Setono\SyliusCMSPlugin\Model\Snippet;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyProviderInterface;

/**
 * A snippet can be placed only in a slot that provides the context it declares: both what its published content needs
 * and what its working copy declares, which it publishes next. See "Snippets and their context" in docs/spec/slots.md
 */
final class DeclaredContextTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @test
     */
    public function it_needs_a_slot_to_provide_what_the_snippet_s_published_content_and_its_working_copy_declare(): void
    {
        $snippet = new Snippet();
        $snippet->setContext(['product']);
        $workingCopy = new Snippet();
        $workingCopy->setContext(['taxon', 'product']);
        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopy($snippet)->willReturn($workingCopy);

        self::assertSame(['product', 'taxon'], (new DeclaredContext($workingCopies->reveal()))->toPlace($snippet));
    }

    /**
     * A snippet that was never published is its own working copy
     *
     * @test
     */
    public function it_needs_what_a_snippet_that_was_never_published_declares(): void
    {
        $snippet = new Snippet();
        $snippet->setContext(['product']);
        $workingCopies = $this->prophesize(WorkingCopyProviderInterface::class);
        $workingCopies->getWorkingCopy($snippet)->willReturn($snippet);

        self::assertSame(['product'], (new DeclaredContext($workingCopies->reveal()))->toPlace($snippet));
    }
}
