<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin;

use Setono\SyliusCMSPlugin\Publishing\EntryReordererInterface;
use Setono\SyliusCMSPlugin\Publishing\InvalidEntrySettingsException;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * What the list of a content type ordered by position posts when editors drag its entries into a new order: their
 * UUIDs, as `entries`, in their new order, and the list's CSRF token. It takes effect at once, and answers with no
 * content, or with why it refused. admin/entry-order.js posts it. See "Entry lists" in docs/spec/admin.md
 */
final readonly class OrderEntriesAction
{
    /** The id of the CSRF token the lists of content types ordered by position carry */
    public const CSRF_TOKEN_ID = 'setono_sylius_cms_entry_order';

    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private ContentTypeRegistryInterface $contentTypes,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private EntryReordererInterface $entryReorderer,
    ) {
    }

    public function __invoke(Request $request, string $contentType): Response
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        if (!$this->contentTypes->isEnabled($contentType)) {
            throw new NotFoundHttpException(sprintf('There is no content type "%s"', $contentType));
        }

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, (string) $request->request->get('_csrf_token')))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        try {
            $this->entryReorderer->reorder($contentType, array_values(array_filter($request->request->all('entries'), is_string(...))));
        } catch (InvalidEntrySettingsException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
