<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Psr\Clock\ClockInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Targeting\CustomerGroupValue;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * Starts the preview as a shopper the entry shows to, at a time it shows. The edited entry ignores its own audience and
 * date window in the preview, but its blocks and everything else on the page follow Shown to and Date.
 * See "Shown to and Date" in docs/spec/targeting.md
 */
final readonly class StartingValues implements StartingValuesInterface
{
    /**
     * @param RepositoryInterface<CustomerGroupInterface> $customerGroupRepository
     */
    public function __construct(
        private RepositoryInterface $customerGroupRepository,
        private ClockInterface $clock,
    ) {
    }

    public function shownTo(EntryInterface $entry): string
    {
        $audience = $entry->getAudience();
        foreach ($this->offered() as $value) {
            if ($audience->allows($value)) {
                return $value;
            }
        }

        // An audience that allows no one Shown to offers, such as only a deleted group, shows the edited entry anyway
        return CustomerGroupValue::GUEST;
    }

    public function date(EntryInterface $entry): ?\DateTimeImmutable
    {
        $from = $entry->getDateWindow()->from;

        return null !== $from && $from > $this->clock->now() ? $from : null;
    }

    /**
     * What Shown to offers, in its order: guests, customers without a group, then the store's customer groups by name.
     * The groups are read only when the audience allows neither guests nor customers without a group
     *
     * @return iterable<string>
     */
    private function offered(): iterable
    {
        yield CustomerGroupValue::GUEST;
        yield CustomerGroupValue::NO_GROUP;

        foreach ($this->customerGroupRepository->findBy([], ['name' => 'ASC']) as $group) {
            $code = $group->getCode();
            if (null !== $code) {
                yield $code;
            }
        }
    }
}
