<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Publishing\DraftLockedException;
use Setono\SyliusCMSPlugin\Repository\RevisionRepositoryInterface;
use Setono\SyliusCMSPlugin\Revision\RevisionRestorerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The overview page's Restore, which copies one of the entry's revisions into its draft, as the builder's restore does,
 * so builders that have the entry open pull it in. It's refused while a scheduled publish locks the draft. See
 * "Entry overview page" in docs/spec/admin.md
 */
final readonly class RestoreRevisionAction
{
    public function __construct(
        private EntryOverviewRequest $overviewRequest,
        private RevisionRepositoryInterface $revisions,
        private RevisionRestorerInterface $restorer,
    ) {
    }

    public function __invoke(Request $request, string $uuid, string $id): RedirectResponse
    {
        return $this->overviewRequest->submit($request, $uuid, function (EntryInterface $entry) use ($id): array {
            $revision = $this->revisions->findOneById((int) $id);
            if (null === $revision || $revision->getEntry()->getUuid() !== $entry->getUuid()) {
                throw new NotFoundHttpException(sprintf('The entry %s has no revision %s', $entry->getUuid(), $id));
            }

            try {
                $this->restorer->restore($revision);
            } catch (DraftLockedException) {
                return ['error', 'setono_sylius_cms.entry_draft_locked'];
            }

            return ['success', 'setono_sylius_cms.revision_restored'];
        });
    }
}
