<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Admin\ExportChoices;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Setono\SyliusCMSPlugin\Transfer\ExportPlannerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The Transfer screen in the Content hub's library. Its export picks entries, found by their title or slug, editor
 * presets and media folders, or everything, in a GET form, lists the snippets and media items that come along because
 * the picked records use them, and downloads the export as a zip (ExportAction). See "Transfer" in docs/spec/admin.md
 */
final readonly class TransferAction
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private ExportRequest $exportRequest,
        private ExportChoices $choices,
        private ExportPlannerInterface $planner,
        private Environment $twig,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $selection = $this->exportRequest->selection($request);
        $search = $request->query->get('q');
        $search = is_string($search) ? trim($search) : '';
        $plan = $selection->isEmpty() ? null : $this->planner->plan($selection);

        return new Response($this->twig->render('@SetonoSyliusCMSPlugin/admin/transfer/index.html.twig', [
            'selection' => $selection,
            'search' => $search,
            'picked_entries' => $this->choices->entries($selection->entries, $request->getLocale()),
            'found_entries' => $this->choices->search($search, $selection->entries, $request->getLocale()),
            'presets' => $this->choices->presets(),
            'folders' => $this->choices->folders(),
            'plan' => $plan,
            'added_entries' => null === $plan ? [] : $this->choices->ofEntries($plan->addedEntries(), $request->getLocale()),
            'added_media_items' => null === $plan ? [] : $this->choices->ofMediaItems($plan->addedMediaItems()),
        ]));
    }
}
