<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Action\Admin;

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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class UploadEditorPictureAction
{
    public function __construct(
        private readonly FactoryInterface $assetFactory,
        private readonly RepositoryInterface $assetRepository,
        private readonly AssetUploaderInterface $assetUploader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
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
                'url' => $this->urlGenerator->generate('setono_sylius_cms_view_asset', ['id' => $asset->getId()]),
            ],
        ]);
    }
}
