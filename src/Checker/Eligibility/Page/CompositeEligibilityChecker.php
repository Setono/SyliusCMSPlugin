<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Checker\Eligibility\Page;

use Setono\CompositeCompilerPass\CompositeService;
use Setono\SyliusCMSPlugin\Model\PageInterface;

/**
 * @extends CompositeService<EligibilityCheckerInterface>
 */
final class CompositeEligibilityChecker extends CompositeService implements EligibilityCheckerInterface
{
    public function isEligible(PageInterface $page): bool
    {
        foreach ($this->services as $service) {
            if (!$service->isEligible($page)) {
                return false;
            }
        }

        return true;
    }
}
