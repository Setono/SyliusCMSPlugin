<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Attribute;

/**
 * Registers a link target: a service implementing Setono\SyliusCMSPlugin\Link\LinkTargetInterface, which Link fields
 * link to. See "Link targets" in docs/spec/extending.md
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsLinkTarget
{
    /**
     * @param string $code stored in each link's "target"; matches ^[a-z][a-z0-9_]*$ and never changes
     * @param string $label a translation key
     * @param bool $translatableRef whether a link stores its ref per locale, as a URL's is, in a Link field whose
     *                              translatable is null. Either way, resolve() gets the current locale's one string
     */
    public function __construct(
        public string $code,
        public string $label,
        public bool $translatableRef = false,
    ) {
    }
}
