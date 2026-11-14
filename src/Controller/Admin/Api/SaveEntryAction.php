<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Builder\ChangeSet\ChangeSet;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\Clash;
use Setono\SyliusCMSPlugin\Builder\ChangeSet\InvalidChangeSetException;
use Setono\SyliusCMSPlugin\Builder\EntryStatus;
use Setono\SyliusCMSPlugin\EntryFormat\EntryNormalizer;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Publishing\AutosaveInterface;
use Setono\SyliusCMSPlugin\Publishing\StaleLockTokenException;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Setono\SyliusCMSPlugin\Security\BuilderCsrfToken;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Autosaves a change set onto the working copy: the rows of a never-published entry, and otherwise its draft. The body
 * carries the lock token from the builder's last load. When colleagues saved since, the save is merged onto theirs, and
 * the answer carries the working copy with their changes, for the builder to pull in, and the clashes
 */
final readonly class SaveEntryAction
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private BuilderCsrfToken $csrfToken,
        private EntryRepositoryInterface $entryRepository,
        private WorkingCopyManagerInterface $workingCopies,
        private AutosaveInterface $autosave,
        private EntryNormalizer $entryNormalizer,
    ) {
    }

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $entry = $this->entryRepository->findOneByUuid($uuid) ?? throw new NotFoundHttpException(sprintf('There is no entry %s', $uuid));

        if (!$this->csrfToken->isValid($request)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        try {
            $body = CanonicalJson::decode($request->getContent());
            $changeSet = ChangeSet::fromArray($body);
            $result = $this->autosave->save($entry, $changeSet, self::lockToken($body));
        } catch (\JsonException|InvalidChangeSetException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (StaleLockTokenException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        $answer = [
            'status' => EntryStatus::of($entry, $this->workingCopies->hasDraft($entry)),
            'lockToken' => $result->saved->lockToken,
        ];

        if ($result->merged) {
            $answer['entry'] = $this->entryNormalizer->normalize($result->saved->workingCopy);
            $answer['clashes'] = array_map(static fn (Clash $clash): ListedObject => new ListedObject($clash->toArray()), $result->clashes);
        }

        return new JsonResponse(CanonicalJson::encode(new ListedObject($answer)), json: true);
    }

    private static function lockToken(mixed $body): int
    {
        $lockToken = is_array($body) ? $body['lockToken'] ?? null : null;
        if (!is_int($lockToken) || $lockToken < 0) {
            throw new InvalidChangeSetException('A save must carry the lock token from the last load, a whole number of 0 or more');
        }

        return $lockToken;
    }
}
