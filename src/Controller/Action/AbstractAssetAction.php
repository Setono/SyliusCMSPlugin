<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Controller\Action;

use Gaufrette\FilesystemInterface;
use Setono\SyliusCMSPlugin\Model\AssetInterface;
use function sprintf;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

abstract class AbstractAssetAction
{
    public function __construct(
        private readonly RepositoryInterface $assetRepository,
        private readonly FilesystemInterface $filesystem,
    ) {
    }

    /**
     * @throws NotFoundHttpException
     */
    protected function getPreparedResponse(int $id, bool $attachment = false): Response
    {
        /** @var AssetInterface|null $asset */
        $asset = $this->assetRepository->find($id);
        if (null === $asset) {
            throw new NotFoundHttpException(sprintf('Asset with id %d does not exist', $id));
        }

        try {
            $file = $this->filesystem->get((string) $asset->getPath());

            $response = new Response($file->getContent());
            $response->headers->set('Content-Type', $asset->getMimeType());

            if ($attachment) {
                $disposition = HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_ATTACHMENT,
                    (string) $asset->getName(),
                );

                $response->headers->set('Content-Disposition', $disposition);
            }

            return $response;
        } catch (FileNotFoundException) {
            throw new NotFoundHttpException(sprintf('Asset with id %d does not exist', $id));
        }
    }
}
