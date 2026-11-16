<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Pagerfanta\Pagerfanta;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Grid\View\GridViewInterface;

/**
 * Listens to the entry resource's index (setono_sylius_cms.entry.index), which the entry lists' grids render through,
 * once the grid has its page of entries. It loads the rows of the page at once, for the grid's field templates.
 * It checks the admin user again, as every CMS admin page does, so a route imported under another prefix doesn't open the lists up
 */
final readonly class EntryIndexListener
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private EntryRows $entryRows,
    ) {
    }

    public function __invoke(ResourceControllerEvent $event): void
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $grid = $event->getSubject();
        $entries = $grid instanceof GridViewInterface ? $grid->getData() : null;
        if ($entries instanceof Pagerfanta) {
            /** @var iterable<EntryInterface> $page the grid is of the entry resource */
            $page = $entries->getCurrentPageResults();
            $this->entryRows->load($page);
        }
    }
}
