<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Action\Admin;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Setono\SyliusCMSPlugin\Model\AssetInterface;
use Setono\SyliusCMSPlugin\Uploader\AssetUploaderInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class UploadEditorPictureAction
{
    private FactoryInterface $assetFactory;

    private RepositoryInterface $assetRepository;

    private AssetUploaderInterface $assetUploader;

    private EventDispatcherInterface $eventDispatcher;

    private CacheManager $cacheManager;

    private string $filter;

    public function __construct(
        FactoryInterface $assetFactory,
        RepositoryInterface $assetRepository,
        AssetUploaderInterface $assetUploader,
        EventDispatcherInterface $eventDispatcher,
        CacheManager $cacheManager,
        string $filter = 'setono_sylius_cms_asset'
    ) {
        $this->assetFactory = $assetFactory;
        $this->assetRepository = $assetRepository;
        $this->assetUploader = $assetUploader;
        $this->eventDispatcher = $eventDispatcher;
        $this->cacheManager = $cacheManager;
        $this->filter = $filter;
    }

    public function __invoke(Request $request): Response
    {
        /** @var UploadedFile|null $uploadedFile */
        $uploadedFile = $request->files->get('image');
        if (null === $uploadedFile) {
            return new JsonResponse([
                'success' => 0,
                'message' => 'An unknown error occurred when trying to upload the image. Please try again.',
            ]);
        }

        // this happens when an uploaded file is larger than the upload_max_filesize ini setting
        if ($uploadedFile->getPathname() === '') {
            return new JsonResponse([
                'success' => 0,
                'message' => sprintf('The uploaded file is larger than %s', ini_get('upload_max_filesize')),
            ]);
        }

        $uploadedFilePath = $this->assetUploader->uploadFile($uploadedFile);
        /** @var AssetInterface $asset */
        $asset = $this->assetFactory->createNew();
        $asset->setName($uploadedFile->getClientOriginalName());
        $asset->setPath($uploadedFilePath);
        $asset->setMimeType($uploadedFile->getMimeType());

        $event = new ResourceControllerEvent($asset);
        $this->eventDispatcher->dispatch($event, 'setono_sylius_cms.asset.pre_create');
        if ($event->isStopped()) {
            throw new HttpException($event->getErrorCode(), $event->getMessage());
        }
        $this->assetRepository->add($asset);

        $event = new ResourceControllerEvent($asset);
        $this->eventDispatcher->dispatch($event, 'setono_sylius_cms.asset.post_create');

        return $event->getResponse() ?? new JsonResponse([
            'success' => 1,
            'file' => [
                'url' => $this->cacheManager->getBrowserPath((string) $asset->getPath(), $this->filter),
            ],
        ]);
    }
}
