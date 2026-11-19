<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preset;

use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Repository\EditorPresetRepositoryInterface;

/**
 * The database deletes the preset's rows of the media usage index with it
 */
final readonly class EditorPresetDeleter implements EditorPresetDeleterInterface
{
    public function __construct(
        private EditorPresetRepositoryInterface $repository,
        private ManagerRegistry $managerRegistry,
    ) {
    }

    public function delete(string $uuid): bool
    {
        $row = $this->repository->findOneByUuid($uuid);
        if (null === $row) {
            return false;
        }

        $manager = EntityManagers::of($this->managerRegistry, $row::class);
        $manager->remove($row);
        $manager->flush();

        return true;
    }
}
