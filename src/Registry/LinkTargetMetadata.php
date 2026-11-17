<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Registry;

use Setono\SyliusCMSPlugin\Link\LinkTargetInterface;

/**
 * A link target as its #[AsLinkTarget] declares it
 */
final readonly class LinkTargetMetadata
{
    /**
     * @param string $label a translation key
     * @param bool $translatableRef whether a link stores its ref per locale, in a Link field whose translatable is null
     */
    public function __construct(
        public string $code,
        public string $label,
        public bool $translatableRef,
        public LinkTargetInterface $target,
    ) {
    }
}
