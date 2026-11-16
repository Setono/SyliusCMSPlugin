<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Repository\ScheduledPublishRepositoryInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A scheduled publish that a validation error or a veto stopped when it ran, an error, with its reason. It isn't
 * retried. It's fixed in the Publishing section of the entry's overview page, with Schedule again. See
 * "Failed scheduled publishes" in docs/spec/publishing.md and "Problems" in docs/spec/admin.md
 */
final readonly class ScheduledPublishFailed implements ProblemKindInterface
{
    public const CODE = 'scheduled_publish_failed';

    public function __construct(private ScheduledPublishRepositoryInterface $scheduledPublishes)
    {
    }

    public function findIn(EntryInterface $entry): iterable
    {
        $scheduledPublish = $this->scheduledPublishes->findOneByEntry($entry);
        if (null === $scheduledPublish || !$scheduledPublish->isFailed()) {
            return [];
        }

        return [new Problem(
            self::CODE,
            Severity::Error,
            $entry,
            new TranslatableMessage('setono_sylius_cms.problem.scheduled_publish_failed', [
                '%at%' => self::time($scheduledPublish->getPublishAt()),
                '%reason%' => $scheduledPublish->getFailureReason() ?? '',
            ]),
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
