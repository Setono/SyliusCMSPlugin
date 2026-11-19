<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Model\ImportInterface;
use Setono\SyliusCMSPlugin\Repository\ImportRepositoryInterface;
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
 * What the pages of an import started in the admin check: an admin user, the import the URL names, and for their forms,
 * the CSRF token the Transfer screen and the import's page put in them. And how the forms answer: on the import's page
 * or back on the Transfer screen, with a flash message when there's something to say. Any admin user can import, as v2
 * has no editor roles. See "Transfer" in docs/spec/admin.md
 *
 * @internal
 */
final readonly class ImportScreenRequest
{
    public const CSRF_TOKEN_ID = 'setono_sylius_cms_import';

    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private ImportRepositoryInterface $imports,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @throws AccessDeniedException for anyone but an admin user
     * @throws NotFoundHttpException when there's no such import
     */
    public function import(string $uuid): ImportInterface
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        return $this->imports->findOneByUuid($uuid) ?? throw new NotFoundHttpException(sprintf('There is no import %s', $uuid));
    }

    /**
     * @throws AccessDeniedHttpException when the form's CSRF token isn't the screen's
     */
    public function checkCsrfToken(Request $request): void
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, (string) $request->request->get('_csrf_token')))) {
            throw new AccessDeniedHttpException('Invalid CSRF token');
        }
    }

    /**
     * @param 'success'|'error' $type
     * @param string $message a key of the flashes domain
     * @param array<string, string> $parameters
     */
    public function flash(Request $request, string $type, string $message, array $parameters = []): void
    {
        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, [] === $parameters ? $message : ['message' => $message, 'parameters' => $parameters]);
        }
    }

    public function toImport(ImportInterface $import): RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate('setono_sylius_cms_admin_import_show', ['uuid' => $import->getUuid()]));
    }

    public function toTransfer(): RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate('setono_sylius_cms_admin_transfer'));
    }
}
