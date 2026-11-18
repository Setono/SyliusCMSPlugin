<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Admin\PresetRows;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The Presets screen in the Content hub's library, in two tabs: starting points, the first, and block presets
 * (?tab=block_presets). Code presets are read-only and marked as coming from code. Editor presets are renamed,
 * recategorised and deleted here, and downloaded as code preset files. Their blocks are changed by saving over them in
 * the builder. See "Presets" in docs/spec/admin.md
 */
final readonly class PresetsAction
{
    public const TAB_STARTING_POINTS = 'starting_points';

    public const TAB_BLOCK_PRESETS = 'block_presets';

    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private PresetRows $rows,
        private Environment $twig,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $tab = self::TAB_BLOCK_PRESETS === $request->query->get('tab') ? self::TAB_BLOCK_PRESETS : self::TAB_STARTING_POINTS;

        return new Response($this->twig->render('@SetonoSyliusCMSPlugin/admin/preset/index.html.twig', [
            'tab' => $tab,
            'tabs' => [self::TAB_STARTING_POINTS, self::TAB_BLOCK_PRESETS],
            'rows' => $this->rows->rows(self::TAB_STARTING_POINTS === $tab),
            'categories' => $this->rows->categories(),
        ]));
    }
}
