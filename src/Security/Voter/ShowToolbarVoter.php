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

    private RequestStack $requestStack;

    private AccessDecisionManagerInterface $accessDecisionManager;

    public function __construct(RequestStack $requestStack, AccessDecisionManagerInterface $accessDecisionManager)
    {
        $this->requestStack = $requestStack;
        $this->accessDecisionManager = $accessDecisionManager;
    }

    /**
     * @param string $attribute
     */
    protected function supports($attribute, $subject): bool
    {
        return self::ATTRIBUTE === $attribute;
    }

    /**
     * @param string $attribute
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        $request = $this->requestStack->getMasterRequest();
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
