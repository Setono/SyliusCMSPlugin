<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

use Setono\SyliusCMSPlugin\Admin\ComingUp;
use Setono\SyliusCMSPlugin\Admin\ComingUpEvent;
use Setono\SyliusCMSPlugin\Admin\EntryRows;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * The cms_admin_* functions of the Overview's panels, which read what each panel shows. The entries a panel lists get
 * their rows in one go, so its template reads each with cms_admin_entry_row(). See "Overview" in docs/spec/admin.md
 *
 * @internal
 */
final readonly class OverviewRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private ComingUp $comingUp,
        private EntryRows $entryRows,
    ) {
    }

    /**
     * @return list<ComingUpEvent>
     */
    public function comingUp(): array
    {
        $events = $this->comingUp->events();
        $this->entryRows->load(array_map(static fn (ComingUpEvent $event) => $event->entry, $events));

        return $events;
    }
}
