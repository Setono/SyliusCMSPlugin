<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Media\MediaFilter;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Repository\MediaFolderRepositoryInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The media items the library shows, newest first, a page at a time: in the folder whose UUID is in `folder`, or in
 * Unsorted for `folder=unsorted`, or else in All media, matching the search in `q`, of the kind in `kind`, and only the
 * images missing alt text in one or more of the store's locales with `missingAlt=1`. See "Search and filters" in
 * docs/spec/media.md
 */
final readonly class MediaIndexAction
{
    public const PER_PAGE = 60;

    public const UNSORTED = 'unsorted';

    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private MediaItemRepositoryInterface $mediaItemRepository,
        private MediaFolderRepositoryInterface $folderRepository,
        private TranslationLocaleProviderInterface $translationLocaleProvider,
        private MediaItemAnswer $answer,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $query = $request->query->all();
        $folder = is_string($query['folder'] ?? null) ? $query['folder'] : '';
        $kind = MediaKind::tryFrom(is_string($query['kind'] ?? null) ? $query['kind'] : '');
        if (null === $kind && '' !== ($query['kind'] ?? '')) {
            return BuilderRequest::badRequest('The kind must be image, video or document');
        }

        $filter = new MediaFilter(
            folder: '' === $folder || self::UNSORTED === $folder ? null : $this->folderRepository->findOneByUuid($folder) ?? throw new NotFoundHttpException(sprintf('There is no media folder %s', $folder)),
            unsorted: self::UNSORTED === $folder,
            search: is_string($query['q'] ?? null) ? $query['q'] : null,
            kind: $kind,
            missingAltIn: $request->query->getBoolean('missingAlt') ? array_values($this->translationLocaleProvider->getDefinedLocalesCodes()) : null,
        );

        $paginator = $this->mediaItemRepository->createLibraryPaginator($filter);
        $paginator->setMaxPerPage(self::PER_PAGE);
        $paginator->setCurrentPage(max(1, min($request->query->getInt('page', 1), $paginator->getNbPages())));

        /** @var list<MediaItemInterface> $mediaItems */
        $mediaItems = iterator_to_array($paginator->getCurrentPageResults(), false);

        return new JsonResponse([
            'items' => array_map($this->answer, $mediaItems),
            'page' => $paginator->getCurrentPage(),
            'pages' => $paginator->getNbPages(),
            'total' => $paginator->getNbResults(),
        ]);
    }
}
