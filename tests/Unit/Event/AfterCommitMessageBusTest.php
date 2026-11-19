<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusCMSPlugin\Event\AfterCommitMessageBus;
use Setono\SyliusCMSPlugin\Media\DeleteReplacedFile;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * The messages that must wait for a transaction to commit, such as the delayed delete of a replaced file, go out at
 * once, or, inside an import's one transaction, once that commits, and never when it rolls back. See "What an import
 * does" in docs/spec/transfer.md
 */
final class AfterCommitMessageBusTest extends TestCase
{
    use ProphecyTrait;

    /** @var \ArrayObject<int, Envelope> what the bus it wraps sent */
    private \ArrayObject $sent;

    /** @var ObjectProphecy<MessageBusInterface> */
    private ObjectProphecy $bus;

    protected function setUp(): void
    {
        $this->bus = $this->prophesize(MessageBusInterface::class);
        $this->sent = $sent = new \ArrayObject();
        $this->bus->dispatch(Argument::any(), Argument::any())->will(static function (array $args) use ($sent): Envelope {
            $message = $args[0];
            \assert(is_object($message));
            $stamps = array_values(array_filter((array) ($args[1] ?? []), static fn (mixed $stamp): bool => $stamp instanceof StampInterface));
            $envelope = Envelope::wrap($message, $stamps);
            $sent->append($envelope);

            return $envelope;
        });
    }

    /**
     * @test
     */
    public function it_sends_at_once_with_its_stamps_while_it_holds_nothing(): void
    {
        $envelope = (new AfterCommitMessageBus($this->bus->reveal()))->dispatch(self::message('a'), [new DelayStamp(1000)]);

        self::assertCount(1, $this->sent);
        self::assertSame(1000, $envelope->last(DelayStamp::class)?->getDelay());
    }

    /**
     * @test
     */
    public function it_holds_the_messages_back_until_it_releases_them_in_the_order_they_came_with_their_stamps(): void
    {
        $bus = new AfterCommitMessageBus($this->bus->reveal());

        $bus->hold();
        $held = $bus->dispatch(self::message('a'), [new DelayStamp(1000)]);
        $bus->dispatch(self::message('b'));
        self::assertCount(0, $this->sent);
        self::assertSame(1000, $held->last(DelayStamp::class)?->getDelay());

        $bus->release();
        self::assertSame(['a', 'b'], array_map(self::path(...), $this->sent->getArrayCopy()));
        self::assertSame(1000, $this->sent->getArrayCopy()[0]->last(DelayStamp::class)?->getDelay());

        // It no longer holds them
        $bus->dispatch(self::message('c'));
        self::assertCount(3, $this->sent);
    }

    /**
     * @test
     */
    public function it_forgets_the_messages_held_back_when_the_transaction_rolls_back(): void
    {
        $bus = new AfterCommitMessageBus($this->bus->reveal());

        $bus->hold();
        $bus->dispatch(self::message('a'));
        $bus->forget();
        $bus->release();

        self::assertCount(0, $this->sent);
    }

    /**
     * @test
     */
    public function it_forgets_them_on_reset_and_refuses_to_hold_twice(): void
    {
        $bus = new AfterCommitMessageBus($this->bus->reveal());

        $bus->hold();
        $bus->dispatch(self::message('a'));
        $bus->reset();
        $bus->release();
        self::assertCount(0, $this->sent);

        $bus->hold();
        $this->expectException(\LogicException::class);
        $bus->hold();
    }

    private static function message(string $path): DeleteReplacedFile
    {
        return new DeleteReplacedFile('0193a5be-70aa-7c11-8e2d-1b2c3d4e5f60', $path);
    }

    private static function path(Envelope $envelope): string
    {
        $message = $envelope->getMessage();
        self::assertInstanceOf(DeleteReplacedFile::class, $message);

        return $message->path;
    }
}
