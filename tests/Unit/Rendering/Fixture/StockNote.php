<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Rendering\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Number;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Field\Toggle;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A block type with a translatable Text field, a shared Text field of upper-case codes, a Number field up to 10 and a
 * field that can't be bound
 */
#[BlockType(code: 'stock_note', label: 'app.block_type.stock_note', template: 'stock_note.html.twig', version: 1)]
final class StockNote
{
    #[Text(label: 'app.field.stock_note.text')]
    public ?string $text = null;

    #[Text(label: 'app.field.stock_note.code', translatable: false)]
    #[Assert\Regex('/^[A-Z0-9_]+$/')]
    public ?string $code = null;

    #[Number(label: 'app.field.stock_note.count')]
    #[Assert\Range(max: 10)]
    public int|float|null $count = null;

    #[Toggle(label: 'app.field.stock_note.highlighted')]
    public bool $highlighted = false;
}
