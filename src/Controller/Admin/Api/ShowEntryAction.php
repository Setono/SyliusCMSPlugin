<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Builder\EntryStatus;
use Setono\SyliusCMSPlugin\Builder\PresenceTrackerInterface;
use Setono\SyliusCMSPlugin\EntryFormat\EntryNormalizer;
use Setono\SyliusCMSPlugin\Json\CanonicalJson;
use Setono\SyliusCMSPlugin\Json\ListedObject;
use Setono\SyliusCMSPlugin\Publishing\AutosaveInterface;
use Setono\SyliusCMSPlugin\Publishing\EntrySettings;
use Setono\SyliusCMSPlugin\Publishing\EntrySettingsNormalizer;
use Setono\SyliusCMSPlugin\Publishing\WorkingCopyManagerInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The working copy (the draft, or the rows of an entry without one) in the entry format, with the lock token a save
 * made on it carries, the entry's on-save settings, which aren't part of the draft, its state, and who else has it open
 */
final readonly class ShowEntryAction
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private EntryRepositoryInterface $entryRepository,
        private WorkingCopyManagerInterface $workingCopies,
        private EntryNormalizer $entryNormalizer,
        private EntrySettingsNormalizer $settingsNormalizer,
        private AutosaveInterface $autosave,
        private PresenceTrackerInterface $presence,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function __invoke(string $uuid): JsonResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $entry = $this->entryRepository->findOneByUuid($uuid) ?? throw new NotFoundHttpException(sprintf('There is no entry %s', $uuid));
        $loaded = $this->autosave->load($entry);

        return new JsonResponse(CanonicalJson::encode(new ListedObject([
            'entry' => $this->entryNormalizer->normalize($loaded->workingCopy),
            'lockToken' => $loaded->lockToken,
            'settings' => $this->settingsNormalizer->normalize(EntrySettings::of($entry)),
            'status' => EntryStatus::of($entry, $this->workingCopies->hasDraft($entry)),
            'editors' => PresenceAction::editors($this->presence->others($entry, $this->admin())),
        ])), json: true);
    }

    private function admin(): UserInterface
    {
        return $this->tokenStorage->getToken()?->getUser() ?? throw new AccessDeniedException('The builder needs a signed-in admin user');
    }
}
