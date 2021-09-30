<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Checker\Eligibility\Page;

use Setono\SyliusCMSPlugin\Model\PageInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * This eligibility checker will allow users to preview pages despite what other eligibility checkers are saying
 */
final class PreviewEligibilityChecker implements EligibilityCheckerInterface
{
    private EligibilityCheckerInterface $eligibilityChecker;

    private RequestStack $requestStack;

    public function __construct(EligibilityCheckerInterface $eligibilityChecker, RequestStack $requestStack)
    {
        $this->eligibilityChecker = $eligibilityChecker;
        $this->requestStack = $requestStack;
    }

    public function isEligible(PageInterface $page): bool
    {
        $request = $this->requestStack->getMasterRequest();
        if (null === $request) {
            return $this->eligibilityChecker->isEligible($page);
        }

        if (!$request->query->has('preview')) {
            return $this->eligibilityChecker->isEligible($page);
        }

        return true;
    }
}
