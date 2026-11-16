<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Setono\SyliusCMSPlugin\Problem\ProblemSummaryProviderInterface;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Gives the Sylius admin menu (sylius.menu.admin.main) one item, Content, which opens the hub. It sits before
 * Configuration, and is current on every page of the hub. Its badge counts the errors and warnings that need
 * attention: Sylius's menu template has no place for a badge, so the count and the worst severity go on the item's
 * link, which admin/menu.css shows as a label. See "Navigation" and "Problems" in docs/spec/admin.md
 */
final readonly class MainMenuListener
{
    public const SECTION = 'setono_sylius_cms';

    public function __construct(
        private ProblemSummaryProviderInterface $problemSummaries,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $content = $menu
            ->addChild(self::SECTION)
            ->setLabel('setono_sylius_cms.menu.admin.main.content.header')
            ->addChild('content', [
                'route' => 'setono_sylius_cms_admin_overview',
                'extras' => ['routes' => [['pattern' => '/^setono_sylius_cms_admin_/']]],
            ])
            ->setLabel('setono_sylius_cms.menu.admin.main.content.content')
            ->setLabelAttribute('icon', 'pencil alternate')
        ;

        $problems = $this->problemSummaries->getSummary()->total();
        if ($problems->total() > 0) {
            $content
                ->setLinkAttribute('data-cms-problems', $problems->errors > 0 ? 'error' : 'warning')
                ->setLinkAttribute('data-cms-problem-count', (string) $problems->total())
                ->setLinkAttribute('title', sprintf(
                    '%s, %s',
                    $this->translator->trans('setono_sylius_cms.ui.error_count', ['%count%' => $problems->errors]),
                    $this->translator->trans('setono_sylius_cms.ui.warning_count', ['%count%' => $problems->warnings]),
                ))
            ;
        }

        $others = array_diff(array_keys($menu->getChildren()), [self::SECTION]);
        $configuration = array_search('configuration', $others, true);
        if (false !== $configuration) {
            array_splice($others, $configuration, 0, self::SECTION);
            $menu->reorderChildren($others);
        }
    }
}
