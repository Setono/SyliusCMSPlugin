<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Targeting;

/**
 * The customers an entry shows to: everyone, only some customer groups, or everyone except some, where guests and
 * signed-in customers without a group count as groups of their own (ADR 0010). The groups are customer group codes,
 * "@guest" and "@no_group". A code whose group was deleted matches nobody, because no shopper has it.
 * See "An entry's audience" in docs/spec/targeting.md
 */
final readonly class Audience
{
    /**
     * Sorted and without duplicates, so equal audiences hold equal lists. Empty for everyone
     *
     * @var list<string>
     */
    public array $groups;

    /**
     * @param iterable<string> $groups customer group codes, "@guest" and "@no_group"
     *
     * @throws \InvalidArgumentException when everyone has groups, or a group is no customer group value
     */
    public function __construct(
        public AudienceMode $mode,
        iterable $groups = [],
    ) {
        $unique = [];
        foreach ($groups as $group) {
            if (!CustomerGroupValue::isValid($group)) {
                throw new \InvalidArgumentException(sprintf('"%s" is neither a customer group code, "%s" nor "%s"', $group, CustomerGroupValue::GUEST, CustomerGroupValue::NO_GROUP));
            }

            $unique[$group] = $group;
        }

        if (AudienceMode::Everyone === $mode && [] !== $unique) {
            throw new \InvalidArgumentException('An audience of everyone has no groups');
        }

        sort($unique, \SORT_STRING);
        $this->groups = $unique;
    }

    public static function everyone(): self
    {
        return new self(AudienceMode::Everyone);
    }

    public static function only(string ...$groups): self
    {
        return new self(AudienceMode::Only, $groups);
    }

    public static function except(string ...$groups): self
    {
        return new self(AudienceMode::Except, $groups);
    }

    /**
     * Whether the entry shows to a shopper with this customer group value. The visible-entries query filters the
     * same way in SQL
     *
     * @param string $customerGroup a customer group code, "@guest" or "@no_group" (see CustomerGroupValue)
     */
    public function allows(string $customerGroup): bool
    {
        return match ($this->mode) {
            AudienceMode::Everyone => true,
            AudienceMode::Only => in_array($customerGroup, $this->groups, true),
            AudienceMode::Except => !in_array($customerGroup, $this->groups, true),
        };
    }

    public function equals(self $other): bool
    {
        return $this->mode === $other->mode && $this->groups === $other->groups;
    }
}
