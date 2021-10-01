<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Checker\Eligibility\Page;

use Setono\SyliusCMSPlugin\Model\PageInterface;

interface EligibilityCheckerInterface
{
    public function isEligible(PageInterface $page): bool;
}
