<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\RichText\RichTextSchema;
use Setono\SyliusCMSPlugin\Value\RichText as RichTextValue;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class RichTextHandler implements SanitizingFieldHandlerInterface, EmptinessCheckingFieldHandlerInterface
{
    public function __construct(private RichTextSchema $schema)
    {
    }

    public function schema(Field $field): array
    {
        return [
            'type' => 'object',
            'properties' => ['type' => ['const' => 'doc'], 'content' => ['type' => 'array']],
            'required' => ['type', 'content'],
        ];
    }

    /**
     * An empty document counts as missing, so a translatable value falls back past it
     */
    public function hydrate(Field $field, mixed $value, HydrationContext $context): ?RichTextValue
    {
        $document = $this->schema->sanitize($value);
        if (null === $document || $this->schema->isEmpty($document)) {
            return null;
        }

        return new RichTextValue($document, $this->schema->render($document));
    }

    public function references(Field $field, mixed $value): iterable
    {
        return [];
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
        if (null === $value || null !== $this->schema->sanitize($value)) {
            return;
        }

        $context->buildViolation('setono_sylius_cms.field.not_rich_text')->addViolation();
    }

    /**
     * A document with nothing in it, such as one empty paragraph, is empty. A value that isn't a document isn't:
     * validate() refuses it
     */
    public function isEmpty(Field $field, mixed $value): bool
    {
        $document = $this->schema->sanitize($value);

        return null !== $document && $this->schema->isEmpty($document);
    }

    /**
     * Drops what the rich text schema doesn't have. A value that isn't a document at all is saved as it is,
     * because invalid content saves: it counts as missing, and publish validates
     */
    public function sanitize(Field $field, mixed $value): mixed
    {
        return $this->schema->sanitize($value) ?? $value;
    }
}
