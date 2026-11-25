<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

/**
 * A choices provider whose choices are the store's records by their codes: its channels, locales and customer groups.
 * A Choice field of such choices refuses a code the store no longer has, as it refuses any choice it no longer offers,
 * except in a block's visibility condition, which keeps it (ChoiceHandler::keepingDeletedRecords()): there it's a
 * broken reference (ADR 0002), which hydrating leaves out and the admin flags (BrokenConditionReference,
 * DeletedCustomerGroup), so publish doesn't refuse it. See "Validation" in docs/spec/publishing.md
 *
 * Only the shipped providers whose records the admin flags in a condition implement it, so a project's own record
 * choices aren't flagged and stay refused once the store no longer has them
 *
 * @internal
 */
interface RecordChoicesProviderInterface extends ChoicesProviderInterface
{
}
