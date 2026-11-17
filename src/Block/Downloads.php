<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\MediaList;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Value\ItemList;

/**
 * A heading and a list of documents to download, such as size guides and manuals, each a link to its permanent URL, so
 * a replaced document needs no edit. See "Downloads" in docs/spec/blocks.md
 */
#[BlockType(
    code: 'downloads',
    label: 'setono_sylius_cms.block_type.downloads',
    template: '@SetonoSyliusCMSPlugin/block/downloads.html.twig',
    version: 1,
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/block/downloads.svg',
)]
final class Downloads
{
    #[Text(label: 'setono_sylius_cms.field.downloads.heading')]
    public ?string $heading = null;

    /** @var ItemList<MediaItemInterface>|null */
    #[MediaList(label: 'setono_sylius_cms.field.downloads.documents', kinds: ['document'])]
    public ?ItemList $documents = null;
}
