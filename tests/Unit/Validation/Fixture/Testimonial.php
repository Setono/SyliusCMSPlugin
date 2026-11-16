<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Validation\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\RichText;
use Setono\SyliusCMSPlugin\Field\Text;
use Setono\SyliusCMSPlugin\Value\RichText as RichTextValue;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A project's block type with Symfony Validator constraints on its fields, a required rich text and a shared field
 */
#[BlockType(code: 'testimonial', label: 'app.testimonial', template: 'testimonial.html.twig')]
final class Testimonial
{
    #[Text(label: 'app.quote')]
    #[Assert\Length(max: 20)]
    #[Assert\Regex('/^[A-Z]/')]
    public ?string $quote = null;

    #[Text(label: 'app.author', translatable: false)]
    #[Assert\NotBlank]
    #[Assert\NotNull]
    public ?string $author = null;

    #[RichText(label: 'app.bio', required: true)]
    public ?RichTextValue $bio = null;
}
