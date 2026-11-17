<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Link;

/**
 * A link target that refuses some refs on publish, such as a URL a link can't lead to
 *
 * @internal
 */
interface RefCheckingLinkTargetInterface extends LinkTargetInterface
{
    /**
     * Why publish refuses the ref, as a translation key in the validators domain, or null when it accepts it
     */
    public function refError(string $ref): ?string;
}
