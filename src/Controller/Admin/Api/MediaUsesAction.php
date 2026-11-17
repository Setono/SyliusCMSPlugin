<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Admin\EntryRows;
use Setono\SyliusCMSPlugin\Model\MediaUsageInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaUsageRepositoryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A media item's Used in list, as the usage index has it: each entry that uses it, with its title as the admin's lists
 * show it, its content type, a link to its overview page, and whether its draft and its published content use it.
 * See "Usage index" in docs/spec/media.md
 */
final readonly class MediaUsesAction
{
    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private MediaItemRepositoryInterface $mediaItemRepository,
        private MediaUsageRepositoryInterface $usages,
        private EntryRows $entryRows,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(string $uuid): JsonResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $mediaItem = $this->mediaItemRepository->findOneByUuid($uuid) ?? throw new NotFoundHttpException(sprintf('There is no media item %s', $uuid));

        $uses = $this->usages->findByMediaItem($mediaItem->getUuid());
        $this->entryRows->load(array_map(static fn (MediaUsageInterface $use): object => $use->getEntry(), $uses));

        return new JsonResponse(['uses' => array_map(function (MediaUsageInterface $use): array {
            $row = $this->entryRows->get($use->getEntry());

            return [
                'entry' => $row->entry->getUuid(),
                'title' => $row->title,
                'contentType' => $this->translator->trans($row->contentType->label),
                'url' => $this->urlGenerator->generate('setono_sylius_cms_admin_entry_show', ['uuid' => $row->entry->getUuid()]),
                'draft' => $use->isInDraft(),
                'published' => $use->isInPublished(),
            ];
        }, $uses)]);
    }
}
