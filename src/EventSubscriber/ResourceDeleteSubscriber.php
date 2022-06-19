<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EventSubscriber;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Setono\MainRequestTrait\MainRequestTrait;
use Sylius\Component\Resource\ResourceActions;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ResourceDeleteSubscriber implements EventSubscriberInterface
{
    use MainRequestTrait;

    private UrlGeneratorInterface $router;

    private SessionInterface $session;

    private array $routes;

    public function __construct(UrlGeneratorInterface $router, SessionInterface $session, array $routes)
    {
        $this->router = $router;
        $this->session = $session;
        $this->routes = $routes;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onResourceDelete',
        ];
    }

    public function onResourceDelete(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof ForeignKeyConstraintViolationException) {
            return;
        }

        if (!$this->isMainRequest($event) || 'html' !== $event->getRequest()->getRequestFormat()) {
            return;
        }

        $eventRequest = $event->getRequest();
        $requestAttributes = $eventRequest->attributes;

        /** @var string $originalRoute */
        $originalRoute = $requestAttributes->get('_route');

        if (!$this->isMethodDelete($eventRequest) || !$this->isExpectedRoute($originalRoute)) {
            return;
        }

        if (null === $requestAttributes->get('_controller')) {
            return;
        }

        /** @var FlashBagInterface $flashBag */
        $flashBag = $this->session->getBag('flashes');
        $flashBag->add('error', 'setono_sylius_cms.resource.delete_error');

        $referrer = $eventRequest->headers->get('referer');
        if (null !== $referrer) {
            $event->setResponse(new RedirectResponse($referrer));

            return;
        }

        $event->setResponse($this->createRedirectResponse($originalRoute, ResourceActions::INDEX));
    }

    private function createRedirectResponse(string $originalRoute, string $targetAction): RedirectResponse
    {
        $redirectRoute = str_replace(ResourceActions::DELETE, $targetAction, $originalRoute);

        return new RedirectResponse($this->router->generate($redirectRoute));
    }

    private function isMethodDelete(Request $request): bool
    {
        return Request::METHOD_DELETE === $request->getMethod();
    }

    private function isExpectedRoute(string $route): bool
    {
        return in_array($route, $this->routes, true);
    }
}
