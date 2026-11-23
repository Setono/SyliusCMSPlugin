<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

/**
 * A choices provider whose choices are the store's records by their codes, such as the channels, the locales and the
 * customer groups a visibility condition names. A Choice field of such choices holds references (ADR 0002): a code the
 * store no longer has still fits, as a deleted product's code does in a Product field, so publish doesn't refuse it.
 * It's a broken reference, which hydrating leaves out and the admin flags in a block's visibility conditions
 * (BrokenConditionReference, DeletedCustomerGroup). See "Validation" in docs/spec/publishing.md
 *
 * Only the shipped providers whose records the admin flags implement it, so a project's own record choices aren't
 * flagged and stay refused once the store no longer has them
 *
 * @internal
 */
interface RecordChoicesProviderInterface extends ChoicesProviderInterface
{
}
