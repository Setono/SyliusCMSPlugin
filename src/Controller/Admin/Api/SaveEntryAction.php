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
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Publishing\AutosaveInterface;
use Setono\SyliusCMSPlugin\Publishing\DraftLockedException;
use Setono\SyliusCMSPlugin\Publishing\StaleLockTokenException;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autosaves a change set onto the working copy: the rows of a never-published entry, and otherwise its draft. The body
 * carries the lock token from the builder's last load. When colleagues saved since, the save is merged onto theirs, and
 * the answer carries the working copy with their changes, for the builder to pull in, and the clashes. While a
 * scheduled publish locks the draft, it saves nothing and answers 423
 */
final readonly class SaveEntryAction
{
    public function __construct(
        private BuilderRequest $builderRequest,
        private WorkingCopyManagerInterface $workingCopies,
        private AutosaveInterface $autosave,
        private EntryNormalizer $entryNormalizer,
    ) {
    }

    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        return $this->builderRequest->change($request, $uuid, fn (EntryInterface $entry): JsonResponse => $this->save($request, $entry));
    }

    private function save(Request $request, EntryInterface $entry): JsonResponse
    {
        try {
            $body = CanonicalJson::decode($request->getContent());
            $changeSet = ChangeSet::fromArray($body);
            $result = $this->autosave->save($entry, $changeSet, self::lockToken($body));
        } catch (\JsonException|InvalidChangeSetException $e) {
            return BuilderRequest::badRequest($e->getMessage());
        } catch (StaleLockTokenException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (DraftLockedException $e) {
            return BuilderRequest::draftLocked($e);
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
