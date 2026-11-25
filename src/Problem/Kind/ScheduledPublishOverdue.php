<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Admin\AdminTimeZone;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\ScheduledPublishInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Problem\TimeDependentProblemKindInterface;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A scheduled publish that hasn't run five minutes after its time, an error: the Messenger worker may not be running,
 * and a missing worker never fails silently. It's fixed in the Publishing section of the entry's overview page, with
 * Publish now or Cancel the schedule. It becomes overdue with time, with nothing written, so the lists and the Content
 * badge find it with a query condition. See "Scheduled publishes" in docs/spec/publishing.md and "Problems" in
 * docs/spec/admin.md
 */
final readonly class ScheduledPublishOverdue implements TimeDependentProblemKindInterface
{
    public const CODE = 'scheduled_publish_overdue';

    public function __construct(
        private ScheduledPublishRepositoryInterface $scheduledPublishes,
        private ClockInterface $clock,
        private AdminTimeZone $timeZone,
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
            new TranslatableMessage('setono_sylius_cms.problem.scheduled_publish_overdue', ['%at%' => $this->timeZone->format($scheduledPublish->getPublishAt())]),
            Fix::inPublishing($entry),
        )];
    }

    public function getSeverity(): Severity
    {
        return Severity::Error;
    }

    public function getCondition(QueryBuilder $queryBuilder, string $entry, \DateTimeImmutable $now): string
    {
        // A scheduled publish whose time is OVERDUE_AFTER or more before now. The column holds UTC
        $overdueAfter = (new \DateTimeImmutable('@0'))->modify(ScheduledPublishInterface::OVERDUE_AFTER)->getTimestamp();
        $queryBuilder->setParameter('cms_problem_overdue_before', $now->setTimezone(new \DateTimeZone('UTC'))->modify(sprintf('-%d seconds', $overdueAfter)), Types::DATETIME_IMMUTABLE);

        return sprintf(
            'EXISTS (SELECT cms_problem_overdue.id FROM %s cms_problem_overdue WHERE cms_problem_overdue.entry = %s AND cms_problem_overdue.failureReason IS NULL AND cms_problem_overdue.publishAt <= :cms_problem_overdue_before)',
            ScheduledPublishInterface::class,
            $entry,
        );
    }
}
