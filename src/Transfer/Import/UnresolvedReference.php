<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Transfer\Import;

/**
 * A reference an imported record holds that doesn't resolve in the target: a Sylius code the target lacks, or a CMS
 * UUID that's neither in the export nor in the target. It imports unchanged, and the admin flags it as a broken
 * reference. See "References that don't resolve" in docs/spec/transfer.md
 */
final readonly class UnresolvedReference
{
    public const CHANNEL = 'channel';

    public const CUSTOMER_GROUP = 'customer_group';

    public const LOCALE = 'locale';

    /**
     * @param string $uuid the record that holds it
     * @param string $label what names that record
     * @param string $type a reference type (Field\ReferenceType), or channel, customer_group or locale
     * @param string $id the code or UUID it refers by
     */
    public function __construct(
        public string $uuid,
        public string $label,
        public string $type,
        public string $id,
    ) {
    }
}
