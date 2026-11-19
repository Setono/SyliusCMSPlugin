<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * How an import ended. See "Commands" in docs/spec/transfer.md
 */
enum ImportStatus: string
{
    /** It applied the plan, and committed */
    case Applied = 'applied';

    /** A dry run that would apply: it checked everything the real run would, and changed nothing */
    case WouldApply = 'would_apply';

    /** It refused, for the plan's conflicts or reasons, and changed nothing */
    case Refused = 'refused';

    /** What it read isn't an export it can read, so there's no plan */
    case Invalid = 'invalid';
}
