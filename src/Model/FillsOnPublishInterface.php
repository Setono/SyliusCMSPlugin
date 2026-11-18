<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

/**
 * An entry whose content type fills values of its own when it's published, such as a blog post's date on its first
 * publish. A publish calls it on the rows, after it copied the draft onto them and before it stores the revision, so the
 * revision and what shoppers see hold the filled values. See "Publishing now" in docs/spec/publishing.md
 */
interface FillsOnPublishInterface extends EntryInterface
{
    /**
     * @param \DateTimeImmutable $publishedAt when it's published, in UTC
     */
    public function fillOnPublish(\DateTimeImmutable $publishedAt): void;
}
