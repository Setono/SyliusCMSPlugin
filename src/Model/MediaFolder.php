<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Symfony\Component\Uid\Uuid;

class MediaFolder implements MediaFolderInterface
{
    protected ?int $id = null;

    protected string $uuid;

    protected string $name = '';

    protected ?MediaFolderInterface $parent = null;

    public function __construct()
    {
        $this->uuid = Uuid::v7()->toRfc4122();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getParent(): ?MediaFolderInterface
    {
        return $this->parent;
    }

    public function setParent(?MediaFolderInterface $parent): void
    {
        $this->parent = $parent;
    }

    public function contains(MediaFolderInterface $folder): bool
    {
        for ($ancestor = $folder; null !== $ancestor; $ancestor = $ancestor->getParent()) {
            if ($ancestor === $this) {
                return true;
            }
        }

        return false;
    }
}
