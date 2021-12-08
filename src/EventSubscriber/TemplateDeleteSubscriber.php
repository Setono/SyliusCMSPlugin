<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EventSubscriber;

use Setono\SyliusCMSPlugin\Model\TemplateInterface;
use Setono\SyliusCMSPlugin\Model\ViewInterface;
use Setono\SyliusCMSPlugin\Repository\ViewRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

/**
 * We have separate subscriber for Template resources deletion
 * as we don't have constraint in database like we have for other resources
 */
final class TemplateDeleteSubscriber implements EventSubscriberInterface
{
    private ViewRepositoryInterface $viewRepository;

    private SessionInterface $session;

    private UrlGeneratorInterface $router;

    public function __construct(
        ViewRepositoryInterface $viewRepository,
        SessionInterface $session,
        UrlGeneratorInterface $router
    ) {
        $this->viewRepository = $viewRepository;
        $this->session = $session;
        $this->router = $router;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'setono_sylius_cms.template.pre_delete' => 'onTemplatePreDelete',
        ];
    }

    public function onTemplatePreDelete(ResourceControllerEvent $event): void
    {
        /** @var TemplateInterface|mixed $template */
        $template = $event->getSubject();
        Assert::isInstanceOf($template, TemplateInterface::class);

        $viewCodes = array_filter(array_map(function (ViewInterface $view): ?string {
            return $view->getCode();
        }, $this->viewRepository->findByTemplate($template)));

        if (0 === count($viewCodes)) {
            return;
        }

        /** @var FlashBagInterface $flashBag */
        $flashBag = $this->session->getBag('flashes');
        $flashBag->add('error', [
            'message' => 'setono_sylius_cms.template.delete_error',
            'parameters' => [
                '%code%' => $template->getCode(),
                '%views%' => implode(', ', $viewCodes),
            ],
        ]);

        $event->setResponse(
            new RedirectResponse($this->router->generate('setono_sylius_cms_admin_template_index'))
        );

        $event->stopPropagation();
    }
}
