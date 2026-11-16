<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Targeting;

use Sylius\Component\Customer\Model\CustomerInterface;

/**
 * What conditions and audiences match a shopper by: the code of their customer group, or one of two values that no
 * Sylius code can take, because Sylius codes can't contain "@". See "Reserved customer group values"
 * in docs/spec/content-model.md
 */
final class CustomerGroupValue
{
    /** Not signed in */
    public const GUEST = '@guest';

    /** Signed in without a group */
    public const NO_GROUP = '@no_group';

    private function __construct()
    {
    }

    /**
     * Sylius gives a customer at most one group, so a shopper has exactly one value
     */
    public static function of(?CustomerInterface $customer): string
    {
        if (null === $customer) {
            return self::GUEST;
        }

        return $customer->getGroup()?->getCode() ?? self::NO_GROUP;
    }

    public static function isReserved(string $value): bool
    {
        return self::GUEST === $value || self::NO_GROUP === $value;
    }

    /**
     * Whether a value can be a shopper's: a customer group code, which can't contain "@", or a reserved value.
     * A code no group has any more is still one, which matches nobody
     */
    public static function isValid(string $value): bool
    {
        return '' !== $value && (!str_contains($value, '@') || self::isReserved($value));
    }
}
