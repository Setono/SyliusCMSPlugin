<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition\Kind;

use Setono\SyliusCMSPlugin\Field\DateTime;
use Setono\SyliusCMSPlugin\Validation\Constraint\EndsAfterStart;

/**
 * A window that ends before it starts, or where it starts, never shows, so publish refuses it
 */
#[EndsAfterStart(start: 'from', end: 'until')]
final class DateWindowParams
{
    /** Inclusive, or null for no start */
    #[DateTime(label: 'setono_sylius_cms.field.date_window_condition.from')]
    public ?\DateTimeImmutable $from = null;

    /** Exclusive, or null for no end */
    #[DateTime(label: 'setono_sylius_cms.field.date_window_condition.until')]
    public ?\DateTimeImmutable $until = null;
}
