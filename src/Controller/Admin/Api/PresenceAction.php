<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Builder\PresenceTrackerInterface;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\SavedChangeSetRepositoryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Setono\SyliusCMSPlugin\Security\BuilderCsrfToken;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * The builder says every few seconds that the editor still has the entry open, and gets who else does. The answer
 * also carries the working copy's lock token, so a builder that's behind pulls in what colleagues saved
 */
final readonly class PresenceAction
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private BuilderCsrfToken $csrfToken,
        private EntryRepositoryInterface $entryRepository,
        private PresenceTrackerInterface $presence,
        private SavedChangeSetRepositoryInterface $savedChangeSets,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $entry = $this->entryRepository->findOneByUuid($uuid) ?? throw new NotFoundHttpException(sprintf('There is no entry %s', $uuid));

        if (!$this->csrfToken->isValid($request)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $admin = $this->tokenStorage->getToken()?->getUser() ?? throw new AccessDeniedException('The builder needs a signed-in admin user');
        $this->presence->see($entry, $admin);

        return new JsonResponse(CanonicalJson::encode(new ListedObject([
            'editors' => self::editors($this->presence->others($entry, $admin)),
            'lockToken' => $this->savedChangeSets->findLatestLockToken($entry),
        ])), json: true);
    }

    /**
     * @param list<string> $names
     *
     * @return list<ListedObject>
     */
    public static function editors(array $names): array
    {
        return array_map(static fn (string $name): ListedObject => new ListedObject(['name' => $name]), $names);
    }
}
