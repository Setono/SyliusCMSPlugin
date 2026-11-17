<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Taxons as TaxonsField;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Sylius\Component\Core\Model\TaxonInterface;

/**
 * A grid of taxon tiles, each leading to the taxon's page with its first image, through Sylius's own images, and its
 * name. Taxon images aren't media items. See "Taxons" in docs/spec/blocks.md
 */
#[BlockType(
    code: 'taxons',
    label: 'setono_sylius_cms.block_type.taxons',
    template: '@SetonoSyliusCMSPlugin/block/taxons.html.twig',
    version: 1,
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/block/taxons.svg',
)]
final class Taxons
{
    /** @var ItemList<TaxonInterface>|null */
    #[TaxonsField(label: 'setono_sylius_cms.field.taxons.taxons', limit: 8)]
    public ?ItemList $taxons = null;
}
