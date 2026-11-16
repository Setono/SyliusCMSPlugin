<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition\Kind;

use Setono\SyliusCMSPlugin\Field\DateTime;

final class DateWindowParams
{
    /** Inclusive, or null for no start */
    #[DateTime(label: 'setono_sylius_cms.field.date_window_condition.from')]
    public ?\DateTimeImmutable $from = null;

    /** Exclusive, or null for no end */
    #[DateTime(label: 'setono_sylius_cms.field.date_window_condition.until')]
    public ?\DateTimeImmutable $until = null;
}
