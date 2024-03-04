<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EventSubscriber;

use Setono\SyliusCMSPlugin\Event\ElementRenderedEvent;
use Setono\SyliusCMSPlugin\Security\Voter\ShowToolbarVoter;
use Setono\SyliusCMSPlugin\Stack\RenderedElementStackInterface;
use Sylius\Bundle\CoreBundle\SectionResolver\SectionProviderInterface;
use Sylius\Bundle\ShopBundle\SectionResolver\ShopSection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

final class AddToolbarSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly RenderedElementStackInterface $elementStack,
        private readonly SectionProviderInterface $sectionProvider,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ElementRenderedEvent::class => 'addToStack',
            KernelEvents::RESPONSE => ['add', -1000],
        ];
    }

    public function add(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
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

    public function addToStack(ElementRenderedEvent $event): void
    {
        $this->elementStack->push($event->element);
    }
}
