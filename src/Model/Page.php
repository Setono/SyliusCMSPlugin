<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Setono\SyliusCMSPlugin\Attribute\Allow;
use Setono\SyliusCMSPlugin\Attribute\ContentType;
use Setono\SyliusCMSPlugin\Tree\StoredLayout;
use Setono\SyliusCMSPlugin\Tree\StoredTree;

/**
 * The built-in content type for standalone pages with their own URL and a block tree
 */
#[ContentType(
    code: 'page',
    label: 'setono_sylius_cms.content_type.page',
    prefix: '',
    blocks: [new Allow('*')],
)]
class Page extends Entry implements PageInterface
{
    /**
     * "Homepage of", an on-save setting: the rows of the channels it's the homepage of
     *
     * @var Collection<array-key, Homepage>
     */
    protected Collection $homepages;

    public function __construct()
    {
        parent::__construct();

        $this->homepages = new ArrayCollection();

        // Page's shipped default layout. Created through its resource factory, a page starts in the default layout the
        // config resolves, which a project can change (see Factory\EntryFactory)
        $this->setBlockTree(new StoredTree(new StoredLayout('standard', 1), []));
    }

    /**
     * A copy reads the original's homepage rows through a collection of its own, as it does its channels
     */
    public function __clone()
    {
        parent::__clone();

        $this->homepages = new ArrayCollection($this->homepages->toArray());
    }

    public function getHomepages(): Collection
    {
        return $this->homepages;
    }

    public function hasHomepage(Homepage $homepage): bool
    {
        return $this->homepages->contains($homepage);
    }

    public function addHomepage(Homepage $homepage): void
    {
        if ($this !== $homepage->getPage()) {
            throw new \LogicException(sprintf('The homepage row of the channel %s belongs to another page', $homepage->getChannelCode()));
        }

        if (!$this->hasHomepage($homepage)) {
            $this->homepages->add($homepage);
        }
    }

    public function removeHomepage(Homepage $homepage): void
    {
        $this->homepages->removeElement($homepage);
    }

    public function getHomepageChannelCodes(): array
    {
        $codes = $this->homepages->map(static fn (Homepage $homepage): string => $homepage->getChannelCode())->getValues();
        sort($codes, \SORT_STRING);

        return $codes;
    }

    public function isHomepageOf(string $channelCode): bool
    {
        return in_array($channelCode, $this->getHomepageChannelCodes(), true);
    }

    protected function createTranslation(): PageTranslationInterface
    {
        return new PageTranslation();
    }
}
