<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Security\Voter;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Webmozart\Assert\Assert;

final class ShowToolbarVoter extends Voter
{
    public const ATTRIBUTE = 'setono-sylius-cms:toolbar:show';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    protected function supports(string $attribute, $subject): bool
    {
        return self::ATTRIBUTE === $attribute;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            return false;
        }

        $session = $request->getSession();
        if (!$session->has('_security_admin')) {
            return false;
        }

        $serializedData = $session->get('_security_admin');
        Assert::string($serializedData);

        $adminToken = unserialize($serializedData, ['allowed_classes' => true]);
        if (!$adminToken instanceof TokenInterface) {
            return false;
        }

        if (!$this->accessDecisionManager->decide($adminToken, ['ROLE_ADMINISTRATION_ACCESS'])) {
            return false;
        }

        return true;
    }
}
