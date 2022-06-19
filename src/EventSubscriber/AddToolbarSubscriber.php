<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EventSubscriber;

use Setono\MainRequestTrait\MainRequestTrait;
use Setono\SyliusCMSPlugin\Security\Voter\ShowToolbarVoter;
use Setono\SyliusCMSPlugin\Stack\ElementStackInterface;
use Sylius\Bundle\CoreBundle\SectionResolver\SectionProviderInterface;
use Sylius\Bundle\ShopBundle\SectionResolver\ShopSection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

final class AddToolbarSubscriber implements EventSubscriberInterface
{
    use MainRequestTrait;

    private Environment $twig;

    private ElementStackInterface $elementStack;

    private SectionProviderInterface $sectionProvider;

    private AuthorizationCheckerInterface $authorizationChecker;

    public function __construct(
        Environment $twig,
        ElementStackInterface $elementStack,
        SectionProviderInterface $sectionProvider,
        AuthorizationCheckerInterface $authorizationChecker
    ) {
        $this->twig = $twig;
        $this->elementStack = $elementStack;
        $this->sectionProvider = $sectionProvider;
        $this->authorizationChecker = $authorizationChecker;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['add', -1000],
        ];
    }

    public function add(ResponseEvent $event): void
    {
        if (!$this->isMainRequest($event)) {
            return;
        }

        if (!$this->sectionProvider->getSection() instanceof ShopSection) {
            return;
        }

        if (!$this->elementStack->hasElements()) {
            return;
        }

        if (!$this->authorizationChecker->isGranted(ShowToolbarVoter::ATTRIBUTE)) {
            return;
        }

        $response = $event->getResponse();
        $content = $response->getContent();
        if (false === $content) {
            return;
        }

        $request = $event->getRequest();

        $position = null;
        if ($request->cookies->has('sscms_toolbar')) {
            $position = $request->cookies->get('sscms_toolbar');
        }

        $toolbar = $this->twig->render('@SetonoSyliusCMSPlugin/toolbar.html.twig', [
            'elements' => $this->elementStack,
            'position' => $position,
        ]);

        $content = str_replace('</body>', $toolbar . '</body>', $content);

        $response->setContent($content);
    }
}
