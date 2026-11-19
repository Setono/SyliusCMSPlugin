<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Listens to the entry resource's show (setono_sylius_cms.entry.show), which the entry overview page renders through.
 * It checks the admin user again, as every CMS admin page does, so a route imported under another prefix doesn't open
 * the overview page up. An entry of a content type switched off in setono_sylius_cms.disabled.content_types has none
 */
final readonly class EntryShowListener
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private ContentTypeRegistryInterface $contentTypes,
    ) {
    }

    public function __invoke(ResourceControllerEvent $event): void
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $entry = $event->getSubject();
        if ($entry instanceof EntryInterface && $this->contentTypes->getByEntry($entry)->disabled) {
            throw new NotFoundHttpException(sprintf('The entry %s is of a content type that is switched off', $entry->getUuid()));
        }
    }
}
