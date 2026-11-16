<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;

/**
 * One channel a page is the homepage of, by the channel's code (ADR 0002), stored on the CMS side so projects don't
 * extend Sylius's Channel entity. A channel has at most one. Read and change a page's through PageInterface.
 * See "Homepage" in docs/spec/urls-and-seo.md
 */
interface HomepageInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getPage(): PageInterface;

    public function getChannelCode(): string;
}
