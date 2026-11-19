<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Setono\SyliusCMSPlugin\Event\AfterCommitDispatcher;
use Setono\SyliusCMSPlugin\Event\EntryCreated;
use Setono\SyliusCMSPlugin\Event\EntryPublished;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * The events of services that dispatch once their transaction commits go out at once, or, inside an import's one
 * transaction, once that commits, and never when it rolls back
 */
final class AfterCommitDispatcherTest extends TestCase
{
    /** @var list<object> */
    private array $heard = [];

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        foreach ([EntryCreated::class, EntryPublished::class] as $event) {
            $this->eventDispatcher->addListener($event, function (object $event): void {
                $this->heard[] = $event;
            });
        }
    }

    /**
     * @test
     */
    public function it_dispatches_at_once_while_it_holds_nothing(): void
    {
        $created = new EntryCreated('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'page');

        self::assertSame($created, (new AfterCommitDispatcher($this->eventDispatcher))->dispatch($created));
        self::assertSame([$created], $this->heard);
    }

    /**
     * @test
     */
    public function it_holds_the_events_back_until_it_releases_them_in_the_order_they_came(): void
    {
        $dispatcher = new AfterCommitDispatcher($this->eventDispatcher);
        $created = new EntryCreated('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'page');
        $published = new EntryPublished('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'page', [], ['en_US' => 'autumn-sale'], false);

        $dispatcher->hold();
        $dispatcher->dispatch($created);
        $dispatcher->dispatch($published);
        self::assertSame([], $this->heard);

        $dispatcher->release();
        self::assertSame([$created, $published], $this->heard);

        // It no longer holds them
        $dispatcher->dispatch($created);
        self::assertCount(3, $this->heard);
    }

    /**
     * @test
     */
    public function it_forgets_the_events_held_back_when_the_transaction_rolls_back(): void
    {
        $dispatcher = new AfterCommitDispatcher($this->eventDispatcher);

        $dispatcher->hold();
        $dispatcher->dispatch(new EntryCreated('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'page'));
        $dispatcher->forget();
        $dispatcher->release();

        self::assertSame([], $this->heard);
    }

    /**
     * @test
     */
    public function it_refuses_to_hold_inside_a_hold(): void
    {
        $dispatcher = new AfterCommitDispatcher($this->eventDispatcher);
        $dispatcher->hold();

        $this->expectException(\LogicException::class);
        $dispatcher->hold();
    }

    /**
     * @test
     */
    public function it_forgets_what_it_holds_when_it_is_reset_between_messages(): void
    {
        $dispatcher = new AfterCommitDispatcher($this->eventDispatcher);
        $dispatcher->hold();
        $dispatcher->dispatch(new EntryCreated('0193a5be-71b0-7a3c-9d4e-5f6a7b8c9d0e', 'page'));

        $dispatcher->reset();
        $dispatcher->hold();
        $dispatcher->release();

        self::assertSame([], $this->heard);
    }
}
