<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EventSubscriber;

use Setono\SyliusCMSPlugin\Stack\ElementStackInterface;
use Sylius\Bundle\CoreBundle\SectionResolver\SectionProviderInterface;
use Sylius\Bundle\ShopBundle\SectionResolver\ShopSection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

final class AddToolbarSubscriber implements EventSubscriberInterface
{
    private Environment $twig;

    private ElementStackInterface $elementStack;

    private SectionProviderInterface $sectionProvider;

    public function __construct(
        Environment $twig,
        ElementStackInterface $elementStack,
        SectionProviderInterface $sectionProvider
    ) {
        $this->twig = $twig;
        $this->elementStack = $elementStack;
        $this->sectionProvider = $sectionProvider;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['add', -1000],
        ];
    }

    public function add(ResponseEvent $event): void
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        if (!$this->sectionProvider->getSection() instanceof ShopSection) {
            return;
        }

        if (!$this->elementStack->hasElements()) {
            return;
        }

        // todo we need to fix this. It doesn't work like this because we have multiple firewalls, i.e. multiple contexts
        //if(!$this->authorizationChecker->isGranted('ROLE_ADMINISTRATION_ACCESS')) {
        //    return;
        //}

        $response = $event->getResponse();
        $content = $response->getContent();
        if (false === $content) {
            return;
        }

        $toolbar = $this->twig->render('@SetonoSyliusCMSPlugin/toolbar.html.twig', [
            'elements' => $this->elementStack,
        ]);

        $content = str_replace('</body>', $toolbar . '</body>', $content);

        $response->setContent($content);
    }
}
