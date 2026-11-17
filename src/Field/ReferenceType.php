<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

/**
 * The types of reference the shipped field types and link targets hold, which their reference loaders are registered
 * under (the type attribute of a setono_sylius_cms.reference_type_loader tag) and the reference_type.* translations
 * name. See "References" in docs/spec/content-model.md
 *
 * @internal
 */
final class ReferenceType
{
    public const ENTRY = 'entry';

    public const MEDIA_ITEM = 'media_item';

    public const PRODUCT = 'product';

    public const TAXON = 'taxon';

    private function __construct()
    {
    }
}
