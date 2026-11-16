<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition;

/**
 * What a condition kind reads to decide whether it matches, which a later cache needs to know: output that read the
 * customer is never served from a shared cache to someone in a different customer group, and output that read the time
 * expires at its next change. See "Caching contract" in docs/spec/targeting.md
 */
enum Reads: string
{
    /** The host-page context, such as the product on a product page */
    case HostPage = 'host_page';

    case Channel = 'channel';

    case Locale = 'locale';

    /** The customer, and with them their customer group */
    case Customer = 'customer';

    /** The current time. A kind that reads it implements TimeDependentConditionKindInterface */
    case Time = 'time';
}
