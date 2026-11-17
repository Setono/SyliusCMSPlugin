<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Handles Snippet fields: a snippet's UUID, hydrated as the snippet from the references loaded before rendering, which
 * hold only the entries the shopper may see. A snippet that isn't visible, or was deleted, and an entry that isn't a
 * snippet, hydrate as missing, so a Snippet block renders nothing
 */
final class SnippetHandler implements FitCheckingFieldHandlerInterface
{
    public function schema(Field $field): array
    {
        return ['type' => 'string', 'format' => 'uuid'];
    }

    public function hydrate(Field $field, mixed $value, HydrationContext $context): ?SnippetInterface
    {
        $snippet = SnippetReferences::isUuid($value) ? $context->references->get(SnippetReferences::TYPE, strtolower($value)) : null;

        return $snippet instanceof SnippetInterface ? $snippet : null;
    }

    public function fits(Field $field, mixed $value): bool
    {
        return SnippetReferences::isUuid($value);
    }

    public function references(Field $field, mixed $value): iterable
    {
        return SnippetReferences::isUuid($value) ? [SnippetReferences::reference($value)] : [];
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
        if (null === $value || SnippetReferences::isUuid($value)) {
            return;
        }

        $context->buildViolation('setono_sylius_cms.field.not_snippet')->addViolation();
    }
}
