<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Publishing;

use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Validation\ValidationError;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Runs a scheduled publish in the Messenger worker, when no editor is watching. A publish that validation or a
 * BeforeEntryPublish listener stops isn't retried: the scheduled publish is flagged as failed with the reason, in the
 * store's default locale, and the message is done. Anything else that goes wrong is left to Messenger's retries, and
 * a scheduled publish that never runs shows as overdue. See "Failed scheduled publishes" in docs/spec/publishing.md
 */
final readonly class ScheduledPublishDueHandler
{
    public function __construct(
        private EntryRepositoryInterface $entries,
        private EntryPublisherInterface $publisher,
        private PublishSchedulerInterface $scheduler,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(ScheduledPublishDue $message): void
    {
        // An entry deleted since took its scheduled publish with it
        $entry = $this->entries->findOneByUuid($message->entryUuid);
        if (null === $entry) {
            return;
        }

        try {
            $this->publisher->publishScheduled($entry, $message->token);
        } catch (InvalidEntryException $e) {
            $this->scheduler->fail($entry, $message->token, $this->translator->trans('setono_sylius_cms.scheduled_publish.invalid', [
                '%count%' => count($e->errors),
                '%errors%' => implode(' ', array_map(static fn (ValidationError $error): string => $error->message, $e->errors)),
            ]));
        } catch (PublishVetoedException $e) {
            $this->scheduler->fail($entry, $message->token, $e->getMessage());
        }
    }
}
