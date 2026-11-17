<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

/**
 * The host-page object a snippet's preview shows it with, such as a product: the context key it's the object of, and
 * how it's referred to, a product's or a taxon's code or an entry's UUID (ADR 0002). Its page is the host page of the
 * placement the preview shows, and the snippet's bindings show its values. See "Previewing a snippet" in
 * docs/spec/slots.md
 */
final readonly class SampleObject
{
    public function __construct(
        public string $key,
        public string $ref,
    ) {
    }
}
