<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Builder\ChangeSet\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\RichText;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Value\RichText as RichTextValue;

/**
 * A block type whose rich text is shared by all translations
 */
#[BlockType(code: 'shared_note', label: 'app.shared_note', template: 'shared_note.html.twig')]
final class SharedNote
{
    #[RichText(label: 'app.body', translatable: false)]
    public ?RichTextValue $body = null;

    #[Text(label: 'app.caption', translatable: false)]
    public ?string $caption = null;
}
