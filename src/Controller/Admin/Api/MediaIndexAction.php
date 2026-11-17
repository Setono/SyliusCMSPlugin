<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Admin\Api;

use Setono\SyliusCMSPlugin\Media\MediaFilter;
use Setono\SyliusCMSPlugin\Media\MediaKind;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Repository\MediaItemRepositoryInterface;
use Setono\SyliusCMSPlugin\Security\AdminAccessChecker;
use Sylius\Resource\Translation\Provider\TranslationLocaleProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * The media items the library shows, newest first, a page at a time: in the folder whose UUID is in `folder`, or in
 * Unsorted for `folder=unsorted`, or else in All media, matching the search in `q`, of the kinds in `kind` (such as
 * `image,document` for a media field that takes both), only the images missing alt text in one or more of the store's
 * locales with `missingAlt=1`, only the media items nothing uses with `unused=1`, and only the media items with the
 * UUIDs in `uuids`, which the builder's media fields look up the media items they hold with. Each comes with its usage
 * count. See "Search and filters" in docs/spec/media.md
 */
final readonly class MediaIndexAction
{
    public const PER_PAGE = 60;

    public const UNSORTED = 'unsorted';

    public function __construct(
        private AdminAccessChecker $adminAccessChecker,
        private MediaItemRepositoryInterface $mediaItemRepository,
        private MediaLookup $lookup,
        private TranslationLocaleProviderInterface $translationLocaleProvider,
        private MediaItemAnswer $answer,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->adminAccessChecker->denyUnlessAdmin();

        $query = $request->query->all();
        $folder = is_string($query['folder'] ?? null) ? $query['folder'] : '';

        $kinds = [];
        foreach (self::list($query['kind'] ?? null) as $code) {
            $kind = MediaKind::tryFrom($code);
            if (null === $kind) {
                return BuilderRequest::badRequest('The kind must be image, video or document, or several of them separated by commas');
            }

            $kinds[] = $kind;
        }

        $uuids = isset($query['uuids']) ? self::list($query['uuids']) : null;
        if (null !== $uuids && [] !== array_filter($uuids, static fn (string $uuid): bool => !Uuid::isValid($uuid))) {
            return BuilderRequest::badRequest('The uuids must be media item UUIDs separated by commas');
        }

        $filter = new MediaFilter(
            folder: '' === $folder || self::UNSORTED === $folder ? null : $this->lookup->folder($folder),
            unsorted: self::UNSORTED === $folder,
            search: is_string($query['q'] ?? null) ? $query['q'] : null,
            kinds: $kinds,
            missingAltIn: $request->query->getBoolean('missingAlt') ? array_values($this->translationLocaleProvider->getDefinedLocalesCodes()) : null,
            uuids: $uuids,
            unused: $request->query->getBoolean('unused'),
        );

        $paginator = $this->mediaItemRepository->createLibraryPaginator($filter);
        $paginator->setMaxPerPage(self::PER_PAGE);
        $paginator->setCurrentPage(max(1, min($request->query->getInt('page', 1), $paginator->getNbPages())));

        /** @var list<MediaItemInterface> $mediaItems */
        $mediaItems = iterator_to_array($paginator->getCurrentPageResults(), false);

        return new JsonResponse([
            'items' => $this->answer->list($mediaItems),
            'page' => $paginator->getCurrentPage(),
            'pages' => $paginator->getNbPages(),
            'total' => $paginator->getNbResults(),
        ]);
    }

    /**
     * The values of a parameter that lists them separated by commas, without empty ones
     *
     * @return list<string>
     */
    private static function list(mixed $parameter): array
    {
        return is_string($parameter) ? array_values(array_filter(explode(',', $parameter), static fn (string $value): bool => '' !== $value)) : [];
    }
}
