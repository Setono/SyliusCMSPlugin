<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\EventListener\Doctrine;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Setono\SyliusCMSPlugin\Model\AssetInterface;
use Setono\SyliusCMSPlugin\Repository\AssetRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final class SetAssetCodeListener
{
    public function __construct(private readonly AssetRepositoryInterface $assetRepository)
    {
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->setCode($args);
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $this->setCode($args);
    }

    private function setCode(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof AssetInterface) {
            return;
        }

        if ($entity->getCode() !== null) {
            return;
        }

        $code = $this->sanitizeName($entity->getName() ?? str_replace('/', '', (string) $entity->getPath()));
        if ('' === $code) {
            $code = (string) Uuid::v4();
        }

        $baseCode = $code;

        $i = 1;
        while ($this->assetRepository->findOneByCode($code) !== null) {
            $code = sprintf('%s_%d', $baseCode, $i);

            ++$i;
        }

        $entity->setCode($code);
    }

    private function sanitizeName(string $name): string
    {
        $name = pathinfo($name, \PATHINFO_FILENAME);

        return trim((string) preg_replace('/[_]+/', '_', (string) preg_replace('/\W+/', '_', $name)), '_');
    }
}
