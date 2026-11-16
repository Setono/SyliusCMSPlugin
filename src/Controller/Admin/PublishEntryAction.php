<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Publishing\EntryPublisherInterface;
use Setono\SyliusCMSPlugin\Publishing\InvalidEntryException;
use Setono\SyliusCMSPlugin\Publishing\PublishVetoedException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The overview page's Publish, which publishes now, as the builder's Publish does. A publish that validation refuses
 * says how many errors need fixing, and one that a BeforeEntryPublish listener vetoes says the listener's message.
 * See "Entry overview page" in docs/spec/admin.md
 */
final readonly class PublishEntryAction
{
    public function __construct(
        private EntryOverviewRequest $overviewRequest,
        private EntryPublisherInterface $entryPublisher,
    ) {
    }

    public function __invoke(Request $request, string $uuid): RedirectResponse
    {
        return $this->overviewRequest->submit($request, $uuid, function (EntryInterface $entry): array {
            try {
                $this->entryPublisher->publish($entry);
            } catch (InvalidEntryException $e) {
                return ['error', ['message' => 'setono_sylius_cms.entry_publish_invalid', 'parameters' => ['%count%' => count($e->errors)]]];
            } catch (PublishVetoedException $e) {
                return ['error', $e->getMessage()];
            }

            return ['success', 'setono_sylius_cms.entry_published'];
        });
    }
}
