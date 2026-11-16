<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Builder\EntryStatus;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Publishing\EntryPublisherInterface;
use Setono\SyliusCMSPlugin\Publishing\InvalidEntryException;
use Setono\SyliusCMSPlugin\Publishing\PublishVetoedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Publishes now. A publish that validation refuses answers 422 with the errors, as entry_validate lists them, and one
 * that a BeforeEntryPublish listener vetoes answers 422 with the listener's message
 */
final readonly class PublishEntryAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private EntryPublisherInterface $entryPublisher,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        return $this->builderRequest->change($request, $uuid, $this->publish(...));
    }

    private function publish(EntryInterface $entry): JsonResponse
    {
        try {
            $this->entryPublisher->publish($entry);
        } catch (InvalidEntryException $e) {
            return new JsonResponse([
                'error' => $this->translator->trans('setono_sylius_cms.publish.invalid', ['%count%' => count($e->errors)]),
                'errors' => $e->errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (PublishVetoedException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // A publish leaves no draft
        return new JsonResponse(['status' => EntryStatus::of($entry, false)->value]);
    }
}
