<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Checker\Eligibility\Page;

use DateTimeImmutable;
use Setono\SyliusCMSPlugin\Model\PageInterface;

final class EnabledDateEligibilityChecker implements EligibilityCheckerInterface
{
    public function isEligible(PageInterface $page): bool
    {
        return $page->isEnabledOn(new DateTimeImmutable());
    }
}
