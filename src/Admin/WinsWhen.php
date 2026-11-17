<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

/**
 * Where a placement wins its slot, by its place among the slot's placements, as the Placements section of its
 * snippet's overview page says it. See "Placements" in docs/spec/admin.md and "Capped slots" in docs/spec/targeting.md
 */
enum WinsWhen: string
{
    /** Wherever it matches: its slot isn't capped, or fewer placements than the cap come before it */
    case Matching = 'matching';

    /** Where it matches and fewer than the cap of the placements before it match */
    case FewerAboveMatch = 'fewer_above_match';

    /** Nowhere: as many catch-all placements as the cap come before it, and they match every page and every shopper */
    case Never = 'never';
}
