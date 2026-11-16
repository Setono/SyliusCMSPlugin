<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Publishing\AutosaveInterface;
use Setono\SyliusCMSPlugin\Publishing\InvalidEntryException;
use Setono\SyliusCMSPlugin\Publishing\PublishSchedulerInterface;
use Setono\SyliusCMSPlugin\Publishing\PublishVetoedException;
use Setono\SyliusCMSPlugin\Publishing\ScheduledPublishInThePastException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Schedules a publish of the working copy at a time, given in the body as {"at": "2026-11-27T08:00:00Z"}, in place of
 * the scheduled publish the entry has. It validates first, as publishing does, and answers 422 with the errors or the
 * veto when it refuses, or when the time isn't in the future. It answers like entry_show, so the builder loads the
 * working copy, which the scheduled publish now locks. See "Scheduled publishes" in docs/spec/publishing.md
 */
final readonly class ScheduleEntryAction
{
    private const DATE_TIME = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/';

    public function __construct(
        private BuilderRequest $builderRequest,
        private PublishSchedulerInterface $scheduler,
        private AutosaveInterface $autosave,
        private WorkingCopyAnswer $answer,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        return $this->builderRequest->change($request, $uuid, fn (EntryInterface $entry): JsonResponse => $this->schedule($request, $entry));
    }

    private function schedule(Request $request, EntryInterface $entry): JsonResponse
    {
        try {
            $body = CanonicalJson::decode($request->getContent());
        } catch (\JsonException $e) {
            return BuilderRequest::badRequest($e->getMessage());
        }

        $at = self::at($body);
        if (null === $at) {
            return BuilderRequest::badRequest('A schedule must carry "at", a date and time such as 2026-11-27T08:00:00Z');
        }

        try {
            $this->scheduler->schedule($entry, $at);
        } catch (ScheduledPublishInThePastException) {
            return self::refused($this->translator->trans('setono_sylius_cms.builder.schedule_in_the_past'));
        } catch (InvalidEntryException $e) {
            return self::refused($this->translator->trans('setono_sylius_cms.publish.invalid', ['%count%' => count($e->errors)]), $e);
        } catch (PublishVetoedException $e) {
            return self::refused($e->getMessage());
        }

        return ($this->answer)($entry, $this->autosave->load($entry));
    }

    private static function at(mixed $body): ?\DateTimeImmutable
    {
        $at = is_array($body) ? ($body['at'] ?? null) : null;
        if (!is_string($at) || 1 !== preg_match(self::DATE_TIME, $at)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($at);
        } catch (\Exception) {
            return null;
        }
    }

    private static function refused(string $error, ?InvalidEntryException $invalid = null): JsonResponse
    {
        return new JsonResponse(['error' => $error] + (null === $invalid ? [] : ['errors' => $invalid->errors]), Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
