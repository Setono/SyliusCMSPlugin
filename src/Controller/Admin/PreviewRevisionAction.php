<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\RevisionRepositoryInterface;
use Setono\SyliusCMSPlugin\Revision\RevisionPreviewUnavailableException;
use Setono\SyliusCMSPlugin\Revision\RevisionPreviewUrlGenerator;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The overview page's Preview of a revision, a link that opens the revision read-only in the shop, in the entry's first
 * channel and the first of its locales the revision has a URL in. It signs a new preview token on every click, so the
 * link never expires. It changes nothing, so it needs no CSRF token. When the revision has no page to show, it goes back
 * to the overview page and says why. See "Entry overview page" in docs/spec/admin.md
 */
final readonly class PreviewRevisionAction
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private EntryRepositoryInterface $entryRepository,
        private RevisionRepositoryInterface $revisions,
        private RevisionPreviewUrlGenerator $previewUrlGenerator,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(Request $request, string $uuid, string $id): RedirectResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $entry = $this->entryRepository->findOneByUuid($uuid) ?? throw new NotFoundHttpException(sprintf('There is no entry %s', $uuid));
        $revision = $this->revisions->findOneById((int) $id);
        if (null === $revision || $revision->getEntry()->getUuid() !== $entry->getUuid()) {
            throw new NotFoundHttpException(sprintf('The entry %s has no revision %s', $uuid, $id));
        }

        try {
            return new RedirectResponse($this->previewUrlGenerator->generate($revision));
        } catch (RevisionPreviewUnavailableException $e) {
            $session = $request->getSession();
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('error', $e->getMessage());
            }

            return new RedirectResponse($this->urlGenerator->generate('setono_sylius_cms_admin_entry_show', ['uuid' => $uuid]));
        }
    }
}
