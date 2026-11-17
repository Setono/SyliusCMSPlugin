<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

use Psr\Log\LoggerInterface;

/**
 * Throws what a block or a snippet threw in dev, so the developer sees it, and in production logs it at error with
 * what an editor needs to find it: the entry's UUID, and the block's key and its block type, or the snippet's slot and
 * placement. See "Failures" in docs/spec/rendering.md
 */
final readonly class FailureHandler implements FailureHandlerInterface
{
    /**
     * @param bool $debug whether the exception is thrown: kernel.debug
     */
    public function __construct(
        private LoggerInterface $logger,
        private bool $debug,
    ) {
    }

    public function blockFailed(string $key, string $type, ?string $entry, \Throwable $exception): void
    {
        if ($this->debug) {
            throw $exception;
        }

        $this->logger->error('The block {key} of type {type} in the entry {entry} threw while rendering, so it was left out: {message}', [
            'entry' => $entry,
            'key' => $key,
            'type' => $type,
            'message' => ExceptionMessage::of($exception),
            'exception' => $exception,
        ]);
    }

    public function snippetFailed(PlacementData $placement, \Throwable $exception): void
    {
        if ($this->debug) {
            throw $exception;
        }

        $this->logger->error('The snippet {entry} in the slot {slot} threw while rendering, so it was left out: {message}', [
            'entry' => $placement->snippetUuid,
            'slot' => $placement->slot,
            'placement' => $placement->uuid,
            'message' => ExceptionMessage::of($exception),
            'exception' => $exception,
        ]);
    }
}
