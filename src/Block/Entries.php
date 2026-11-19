<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Block;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Collection\CardDisplayChoicesProvider;
use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Field\Entries as EntriesField;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Value\ItemList;

/**
 * A list of entries of any collection, hand-picked or filled by an entry source, such as the blog's latest posts, each
 * shown through its own content type's card for the block's display. It mirrors the Products block. The builder offers
 * the displays the listed entries' content type has cards for. See "The Entries block" in docs/spec/collections.md
 */
#[BlockType(
    code: 'entries',
    label: 'setono_sylius_cms.block_type.entries',
    template: '@SetonoSyliusCMSPlugin/block/entries.html.twig',
    version: 1,
    thumbnail: '/bundles/setonosyliuscmsplugin/thumbnail/block/entries.svg',
)]
final class Entries
{
    /** @var ItemList<EntryInterface>|null */
    #[EntriesField(label: 'setono_sylius_cms.field.entries.entries', limit: 9)]
    public ?ItemList $entries = null;

    /**
     * How the entries show, each through its content type's card for it, such as grid: the block's root gets the class
     * cms-entries--<display>. A carousel shows 3 at once. The choices are every display a content type has a card for,
     * which the builder narrows to those the listed entries have
     */
    #[Choice(label: 'setono_sylius_cms.field.entries.display', choicesProvider: CardDisplayChoicesProvider::class)]
    public string $display = 'grid';

    /** Whether the list is split into pages of its number to show, with numbered links (#269) */
    #[Toggle(label: 'setono_sylius_cms.field.entries.paginate', help: 'setono_sylius_cms.field.entries.paginate_help')]
    public bool $paginate = false;
}
