<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Context\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Taxons;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Sylius\Component\Core\Model\TaxonInterface;

/**
 * A project's block type whose list is filled per locale
 */
#[BlockType(code: 'localized_picks', label: 'app.block_type.localized_picks', template: 'localized_picks.html.twig', version: 1)]
final class LocalizedPicks
{
    /** @var ItemList<TaxonInterface>|null */
    #[Taxons(label: 'app.field.localized_picks.taxons', translatable: true)]
    public ?ItemList $taxons = null;
}
