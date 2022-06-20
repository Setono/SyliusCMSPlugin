<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Doctrine\Common\Collections\Collection;

interface TagsAwareInterface
{
    /**
     * @return Collection|TagInterface[]
     *
     * @psalm-return Collection<array-key, TagInterface>
     */
    public function getTags(): Collection;

    public function addTag(TagInterface $tag): void;

    public function removeTag(TagInterface $tag): void;

    /**
     * Returns true if the tag is already present
     */
    public function hasTag(TagInterface $tag): bool;
}
