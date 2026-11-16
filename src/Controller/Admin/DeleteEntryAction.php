<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Publishing\DeleteVetoedException;
use Setono\SyliusCMSPlugin\Publishing\EntryDeleterInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The entry lists' delete action. It takes what Sylius's grid delete action sends, a DELETE carrying a CSRF token whose
 * id is the entry's id, and deletes through EntryDeleterInterface, so the delete dispatches BeforeEntryDelete and
 * EntryDeleted, which the resource controller's delete wouldn't. It goes back to the page it was sent from, with the
 * outcome as a flash message, which is a BeforeEntryDelete listener's message when one vetoed the delete
 */
final readonly class DeleteEntryAction
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private EntryRepositoryInterface $entryRepository,
        private EntryDeleterInterface $entryDeleter,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private ContentTypeRegistryInterface $contentTypes,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(Request $request, string $uuid): Response
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $entry = $this->entryRepository->findOneByUuid($uuid) ?? throw new NotFoundHttpException(sprintf('There is no entry %s', $uuid));

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken((string) $entry->getId(), (string) $request->request->get('_csrf_token')))) {
            throw new AccessDeniedHttpException('Invalid CSRF token');
        }

        $back = $this->backUrl($request, $this->contentTypes->getByEntry($entry)->code);

        try {
            $this->entryDeleter->delete($entry);
            $this->flash($request, 'success', 'setono_sylius_cms.entry_deleted');
        } catch (DeleteVetoedException $e) {
            $this->flash($request, 'error', $e->getMessage());
        }

        return new RedirectResponse($back);
    }

    /**
     * The page the delete was sent from, when it's on this host, or else the list of the entry's content type
     */
    private function backUrl(Request $request, string $contentType): string
    {
        $referer = (string) $request->headers->get('referer');
        if (str_starts_with($referer, $request->getSchemeAndHttpHost() . '/')) {
            return $referer;
        }

        return $this->urlGenerator->generate('setono_sylius_cms_admin_entry_index', ['contentType' => $contentType]);
    }

    private function flash(Request $request, string $type, string $message): void
    {
        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }
    }
}
