<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EventSubscriber;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Setono\MainRequestTrait\MainRequestTrait;
use Sylius\Component\Resource\ResourceActions;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ResourceDeleteSubscriber implements EventSubscriberInterface
{
    use MainRequestTrait;

    private UrlGeneratorInterface $router;

    private array $routes;

    public function __construct(UrlGeneratorInterface $router, array $routes)
    {
        $this->router = $router;
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

        $request = $event->getRequest();

        $originalRoute = $request->attributes->get('_route');
        if (!is_string($originalRoute)) {
            return;
        }

        if (Request::METHOD_DELETE !== $request->getMethod() || !in_array($originalRoute, $this->routes, true)) {
            return;
        }

        if (null === $request->attributes->get('_controller')) {
            return;
        }

        $session = $request->getSession();
        if ($session instanceof Session) {
            $session->getFlashBag()->add('error', 'setono_sylius_cms.resource.delete_error');
        }

        $referrer = $request->headers->get('referer');
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
}
