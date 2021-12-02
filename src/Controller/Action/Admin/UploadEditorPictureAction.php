<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Action\Admin;

use HttpException;
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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class UploadEditorPictureAction
{
    private FactoryInterface $assetFactory;

    private RepositoryInterface $assetRepository;

    private AssetUploaderInterface $assetUploader;

    private EventDispatcherInterface $eventDispatcher;

    private CacheManager $cacheManager;

    public function __construct(
        FactoryInterface $assetFactory,
        RepositoryInterface $assetRepository,
        AssetUploaderInterface $assetUploader,
        EventDispatcherInterface $eventDispatcher,
        CacheManager $cacheManager
    ) {
        $this->assetFactory = $assetFactory;
        $this->assetRepository = $assetRepository;
        $this->assetUploader = $assetUploader;
        $this->eventDispatcher = $eventDispatcher;
        $this->cacheManager = $cacheManager;
    }

    public function __invoke(Request $request): Response
    {
        /** @var UploadedFile|null $uploadedFile */
        $uploadedFile = $request->files->get('image');
        if (null === $uploadedFile) {
            throw new BadRequestHttpException('Expected an image');
        }

        $uploadedFilePath = $this->assetUploader->uploadFile($uploadedFile);
        /** @var AssetInterface $asset */
        $asset = $this->assetFactory->createNew();
        $asset->setName($uploadedFile->getClientOriginalName());
        $asset->setPath($uploadedFilePath);
        $asset->setMimeType($uploadedFile->getMimeType());

        $event = $this->eventDispatcher->dispatch(new ResourceControllerEvent($asset), 'setono_sylius_cms.asset.pre_create');
        if ($event->isStopped()) {
            throw new HttpException($event->getErrorCode(), $event->getMessage());
        }
        $this->assetRepository->add($asset);

        $event = $this->eventDispatcher->dispatch(new ResourceControllerEvent($asset), 'setono_sylius_cms.asset.post_create');
        $postEventResponse = $event->getResponse();
        if (null !== $postEventResponse) {
            return $postEventResponse;
        }

        return new JsonResponse([
            'success' => 1,
            'file' => [
                'url' => $this->cacheManager->getBrowserPath($asset->getPath(), 'setono_sylius_cms_asset'),
            ],
        ]);
    }
}
