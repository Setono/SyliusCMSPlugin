<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Profiler;

use Setono\SyliusCMSPlugin\Rendering\ExceptionMessage;
use Setono\SyliusCMSPlugin\Rendering\FailureHandlerInterface;
use Setono\SyliusCMSPlugin\Rendering\PlacementData;

/**
 * Records the blocks, snippets and slots that failed, in the slot rendering now or outside any slot, then hands them on.
 * In dev the handler throws an exception on, and each block it sits in gets it again, wrapped by Twig: only the block
 * that threw it first is recorded. Registered in debug only
 *
 * @internal
 */
final readonly class TraceableFailureHandler implements FailureHandlerInterface
{
    /** @var \WeakMap<\Throwable, true> the exceptions recorded */
    private \WeakMap $recorded;

    public function __construct(
        private FailureHandlerInterface $handler,
        private RenderedSlots $slots,
    ) {
        $this->recorded = new \WeakMap();
    }

    public function blockFailed(string $key, string $type, ?string $entry, \Throwable $exception): void
    {
        $this->record($exception, new RecordedFailure($entry, $key, $type, null, ExceptionMessage::of($exception)));

        $this->handler->blockFailed($key, $type, $entry, $exception);
    }

    public function slotFailed(string $identifier, \Throwable $exception): void
    {
        $this->record($exception, new RecordedFailure(null, null, null, null, ExceptionMessage::of($exception)));

        $this->handler->slotFailed($identifier, $exception);
    }

    public function snippetFailed(PlacementData $placement, \Throwable $exception): void
    {
        $this->record($exception, new RecordedFailure($placement->snippetUuid, null, null, $placement->uuid, ExceptionMessage::of($exception)));

        $this->handler->snippetFailed($placement, $exception);
    }

    public function pageScopeFailed(PlacementData $placement, string $kind, \Throwable $exception): void
    {
        $this->record($exception, new RecordedFailure($placement->snippetUuid, null, null, $placement->uuid, ExceptionMessage::of($exception), $kind));

        $this->handler->pageScopeFailed($placement, $kind, $exception);
    }

    private function record(\Throwable $exception, RecordedFailure $failure): void
    {
        for ($thrown = $exception; null !== $thrown; $thrown = $thrown->getPrevious()) {
            if (isset($this->recorded[$thrown])) {
                return;
            }
        }

        $this->recorded[$exception] = true;
        $this->slots->failed($failure);
    }
}
