<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Publishing\DraftLockedException;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Setono\SyliusCMSPlugin\Security\BuilderCsrfToken;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * What the builder's endpoints check before they do anything: an admin user, the entry the URL names, and the builder's
 * CSRF token for an endpoint that changes something. And the answers they share
 *
 * @internal
 */
final readonly class BuilderRequest
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private BuilderCsrfToken $csrfToken,
        private EntryRepositoryInterface $entryRepository,
        private TokenStorageInterface $tokenStorage,
        private ContentTypeRegistryInterface $contentTypes,
    ) {
    }

    /**
     * The entry the URL names, for an admin user. An entry of a content type switched off in
     * setono_sylius_cms.disabled.content_types is hidden from the builder
     *
     * @throws AccessDeniedException for anyone but an admin user
     * @throws NotFoundHttpException when there's no such entry
     */
    public function entry(string $uuid): EntryInterface
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $entry = $this->entryRepository->findOneByUuid($uuid);
        if (null === $entry || $this->contentTypes->getByEntry($entry)->disabled) {
            throw new NotFoundHttpException(sprintf('There is no entry %s', $uuid));
        }

        return $entry;
    }

    /**
     * Changes the entry the URL names, for an admin user whose request carries the builder's CSRF token, and otherwise
     * answers 403
     *
     * @param callable(EntryInterface): JsonResponse $change
     *
     * @throws AccessDeniedException for anyone but an admin user
     * @throws NotFoundHttpException when there's no such entry
     */
    public function change(Request $request, string $uuid, callable $change): JsonResponse
    {
        $entry = $this->entry($uuid);

        return $this->csrfToken->isValid($request) ? $change($entry) : self::invalidCsrfToken();
    }

    /**
     * Does what an admin user asks for, when their request carries the builder's CSRF token, and otherwise answers 403
     *
     * @param callable(): JsonResponse $work
     *
     * @throws AccessDeniedException for anyone but an admin user
     */
    public function withCsrfToken(Request $request, callable $work): JsonResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        return $this->csrfToken->isValid($request) ? $work() : self::invalidCsrfToken();
    }

    /**
     * The signed-in admin user
     *
     * @throws AccessDeniedException when no one is signed in
     */
    public function admin(): UserInterface
    {
        return $this->tokenStorage->getToken()?->getUser() ?? throw new AccessDeniedException('The builder needs a signed-in admin user');
    }

    public static function badRequest(string $error): JsonResponse
    {
        return new JsonResponse(['error' => $error], Response::HTTP_BAD_REQUEST);
    }

    /**
     * 423 Locked: a scheduled publish locks the draft, so the builder loads it again, read-only
     */
    public static function draftLocked(DraftLockedException $e): JsonResponse
    {
        return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_LOCKED);
    }

    private static function invalidCsrfToken(): JsonResponse
    {
        return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
    }
}
