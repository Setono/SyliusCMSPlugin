<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Field\Text;

#[BlockType(code: 'teaser', label: 'app.teaser', template: 'teaser.html.twig', version: 3)]
final class Teaser
{
    #[Text(label: 'app.static')]
    public static ?string $static = null;

    #[Text(label: 'app.heading')]
    public ?string $heading = null;

    public string $notAField = '';

    #[Text(label: 'app.code', translatable: false)]
    public ?string $code = null;

    #[Choice(label: 'app.size', choices: ['s' => 'Small', 'l' => 'Large'], translatable: true)]
    public string $size = 's';

    #[Text(label: 'app.private')]
    private ?string $private = null; // @phpstan-ignore property.onlyWritten, property.unusedType
}
