<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * What the forms of the entry overview page check before they change anything: an admin user, the entry the URL names,
 * and the CSRF token the page put in the form, whose id is the entry's id, as Sylius's own admin forms do. And how they
 * answer: back on the overview page, with the outcome as a flash message
 *
 * @internal
 */
final readonly class EntryOverviewRequest
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private EntryRepositoryInterface $entryRepository,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Changes the entry the URL names, and goes back to its overview page with the flash message the change returns
     *
     * @param callable(EntryInterface): array{'success'|'error', string|array{message: string, parameters: array<string, mixed>}} $change
     *        returns the flash message's type and the message, a key of the flashes domain or a text, or a key with its parameters
     *
     * @throws AccessDeniedException for anyone but an admin user
     * @throws NotFoundHttpException when there's no such entry
     * @throws AccessDeniedHttpException when the form's CSRF token isn't the page's
     */
    public function submit(Request $request, string $uuid, callable $change): RedirectResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $entry = $this->entryRepository->findOneByUuid($uuid) ?? throw new NotFoundHttpException(sprintf('There is no entry %s', $uuid));

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken((string) $entry->getId(), (string) $request->request->get('_csrf_token')))) {
            throw new AccessDeniedHttpException('Invalid CSRF token');
        }

        [$type, $message] = $change($entry);

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }

        return new RedirectResponse($this->urlGenerator->generate('setono_sylius_cms_admin_entry_show', ['uuid' => $entry->getUuid()]));
    }
}
