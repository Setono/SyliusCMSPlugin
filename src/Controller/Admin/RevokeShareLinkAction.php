<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Repository\ShareLinkRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The overview page's Revoke of one of the entry's share links, which deletes it, as the builder's share_link_revoke
 * does, so its token answers as for a shopper. See "Entry overview page" in docs/spec/admin.md
 */
final readonly class RevokeShareLinkAction
{
    public function __construct(
        private EntryOverviewRequest $overviewRequest,
        private ShareLinkRepositoryInterface $shareLinks,
    ) {
    }

    public function __invoke(Request $request, string $uuid, string $id): RedirectResponse
    {
        return $this->overviewRequest->submit($request, $uuid, function (EntryInterface $entry) use ($id): array {
            $shareLink = $this->shareLinks->findOneById((int) $id);
            if (null === $shareLink || $shareLink->getEntry()->getUuid() !== $entry->getUuid()) {
                throw new NotFoundHttpException(sprintf('The entry %s has no share link %s', $entry->getUuid(), $id));
            }

            $this->shareLinks->remove($shareLink);

            return ['success', 'setono_sylius_cms.share_link_revoked'];
        });
    }
}
