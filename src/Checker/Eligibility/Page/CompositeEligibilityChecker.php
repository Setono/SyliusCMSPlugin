<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Checker\Eligibility\Page;

use Setono\SyliusCMSPlugin\Model\PageInterface;

final class CompositeEligibilityChecker implements EligibilityCheckerInterface
{
    /** @var array<array-key, EligibilityCheckerInterface> */
    private array $eligibilityCheckers = [];

    public function add(EligibilityCheckerInterface $eligibilityChecker): void
    {
        $this->eligibilityCheckers[] = $eligibilityChecker;
    }

    public function isEligible(PageInterface $page): bool
    {
        foreach ($this->eligibilityCheckers as $eligibilityChecker) {
            if (!$eligibilityChecker->isEligible($page)) {
                return false;
            }
        }

        return true;
    }
}
