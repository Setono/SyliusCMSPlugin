<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Targeting;

/**
 * Whom an entry shows to. See "An entry's audience" in docs/spec/targeting.md
 */
enum AudienceMode: string
{
    /** Every shopper */
    case Everyone = 'everyone';

    /** Shoppers whose customer group value is in the list */
    case Only = 'only';

    /** Every shopper whose customer group value isn't in the list */
    case Except = 'except';
}
