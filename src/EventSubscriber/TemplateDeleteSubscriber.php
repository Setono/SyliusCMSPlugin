<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EventSubscriber;

use Setono\SyliusCMSPlugin\Model\TemplateInterface;
use Setono\SyliusCMSPlugin\Model\ViewInterface;
use Setono\SyliusCMSPlugin\Repository\ViewRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Webmozart\Assert\Assert;

/**
 * We have separate subscriber for Template resources deletion
 * as we don't have constraint in database like we have for other resources
 */
final class TemplateDeleteSubscriber implements EventSubscriberInterface
{
    private ViewRepositoryInterface $viewRepository;

    public function __construct(ViewRepositoryInterface $viewRepository)
    {
        $this->viewRepository = $viewRepository;
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

        $viewCodes = array_filter(array_map(static function (ViewInterface $view): ?string {
            return $view->getCode();
        }, $this->viewRepository->findByTemplate($template)));

        if (0 === count($viewCodes)) {
            return;
        }

        $event->setMessage('setono_sylius_cms.template.delete_error');
        $event->setMessageType('error');
        $event->setMessageParameters([
            'message' => 'setono_sylius_cms.template.delete_error',
            'parameters' => [
                '%code%' => $template->getCode(),
                '%views%' => implode(', ', $viewCodes),
            ],
        ]);

        $event->stopPropagation();
    }
}
