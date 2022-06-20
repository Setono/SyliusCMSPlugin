<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

trait TagsAwareTrait
{
    /**
     * @var Collection|TagInterface[]
     *
     * @psalm-var Collection<array-key, TagInterface>
     */
    protected Collection $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    /**
     * @return Collection|TagInterface[]
     *
     * @psalm-return Collection<array-key, TagInterface>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(TagInterface $tag): void
    {
        if (!$this->hasTag($tag)) {
            $this->tags->add($tag);
        }
    }

    public function removeTag(TagInterface $tag): void
    {
        if ($this->hasTag($tag)) {
            $this->tags->removeElement($tag);
        }
    }

    /**
     * Returns true if the tag is already present
     */
    public function hasTag(TagInterface $tag): bool
    {
        return $this->tags->contains($tag);
    }
}
