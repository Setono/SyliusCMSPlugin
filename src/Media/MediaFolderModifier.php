<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Media;

use Doctrine\Persistence\ManagerRegistry;
use Setono\SyliusCMSPlugin\Doctrine\EntityManagers;
use Setono\SyliusCMSPlugin\Model\MediaFolderInterface;
use Setono\SyliusCMSPlugin\Repository\MediaFolderRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;

final readonly class MediaFolderModifier implements MediaFolderModifierInterface
{
    private const MAX_LENGTH = 255;

    /**
     * @param FactoryInterface<MediaFolderInterface> $folderFactory
     */
    public function __construct(
        private FactoryInterface $folderFactory,
        private MediaFolderRepositoryInterface $folderRepository,
        private ManagerRegistry $managerRegistry,
    ) {
    }

    public function create(string $name, ?MediaFolderInterface $parent): MediaFolderInterface
    {
        $folder = $this->folderFactory->createNew();
        $folder->setName(self::name($name));
        $folder->setParent($parent);

        $this->folderRepository->add($folder);

        return $folder;
    }

    public function rename(MediaFolderInterface $folder, string $name): void
    {
        $folder->setName(self::name($name));

        EntityManagers::of($this->managerRegistry, $folder::class)->flush();
    }

    public function move(MediaFolderInterface $folder, ?MediaFolderInterface $parent): void
    {
        if (null !== $parent && $folder->contains($parent)) {
            throw new InvalidMediaChangeException(sprintf('The folder "%s" can\'t go inside itself', $folder->getName()));
        }

        $folder->setParent($parent);

        EntityManagers::of($this->managerRegistry, $folder::class)->flush();
    }

    /**
     * @throws InvalidMediaChangeException
     */
    private static function name(string $name): string
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > self::MAX_LENGTH) {
            throw new InvalidMediaChangeException(sprintf('A folder needs a name of at most %d characters', self::MAX_LENGTH));
        }

        return $name;
    }
}
