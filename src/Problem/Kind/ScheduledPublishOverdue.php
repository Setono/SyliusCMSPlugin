<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A scheduled publish that hasn't run five minutes after its time, an error: the Messenger worker may not be running,
 * and a missing worker never fails silently. It's fixed in the Publishing section of the entry's overview page, with
 * Publish now or Cancel the schedule. See "Scheduled publishes" in docs/spec/publishing.md and "Problems" in docs/spec/admin.md
 */
final readonly class ScheduledPublishOverdue implements ProblemKindInterface
{
    public const CODE = 'scheduled_publish_overdue';

    public function __construct(
        private ScheduledPublishRepositoryInterface $scheduledPublishes,
        private ClockInterface $clock,
    ) {
    }

    public function findIn(EntryInterface $entry): iterable
    {
        $scheduledPublish = $this->scheduledPublishes->findOneByEntry($entry);
        if (null === $scheduledPublish || !$scheduledPublish->isOverdue($this->clock->now())) {
            return [];
        }

        return [new Problem(
            self::CODE,
            Severity::Error,
            $entry,
            new TranslatableMessage('setono_sylius_cms.problem.scheduled_publish_overdue', ['%at%' => self::time($scheduledPublish->getPublishAt())]),
            Fix::inPublishing($entry),
        )];
    }

    /**
     * In PHP's default time zone, as Twig's date filter shows the admin's other times
     */
    private static function time(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i');
    }
}
