<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Doctrine\Common\Collections\Collection;

/**
 * @method PageTranslationInterface getTranslation(?string $locale = null)
 */
interface PageInterface extends EntryInterface
{
    /**
     * "Homepage of": the rows of the channels it's the homepage of, shown at their root URL in place of the store's
     * own homepage. An on-save setting. Change them through HomepageModifierInterface, which creates rows through the
     * homepage resource's factory and takes a channel off the page that was its homepage. They're typed by the shipped
     * model, which a project's own model extends to inherit its Doctrine mapping.
     * See "Homepage" in docs/spec/urls-and-seo.md
     *
     * @return Collection<array-key, Homepage>
     */
    public function getHomepages(): Collection;

    public function hasHomepage(Homepage $homepage): bool;

    /**
     * @throws \LogicException when the row belongs to another page
     */
    public function addHomepage(Homepage $homepage): void;

    public function removeHomepage(Homepage $homepage): void;

    /**
     * The codes of the channels it's the homepage of, sorted
     *
     * @return list<string>
     */
    public function getHomepageChannelCodes(): array;

    public function isHomepageOf(string $channelCode): bool;
}
