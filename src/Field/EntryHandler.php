<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Handles Entry fields: an entry's UUID, hydrated as the entry from the references loaded before rendering, which hold
 * only the entries the shopper may see, so one that isn't visible, was deleted or is of another content type than the
 * field's hydrates as missing. The entry is a link, not a dependency. Its schema's x-cms-options names the code of the
 * field's content type, which the builder's picker searches
 */
final readonly class EntryHandler implements FitCheckingFieldHandlerInterface
{
    public function __construct(private ContentTypeRegistryInterface $contentTypes)
    {
    }

    public function schema(Field $field): array
    {
        $contentType = self::entry($field)->contentType;

        return [
            'type' => 'string',
            'format' => 'uuid',
            'x-cms-options' => ['contentType' => null === $contentType ? null : $this->contentTypes->getByClass($contentType)->code],
        ];
    }

    public function hydrate(Field $field, mixed $value, HydrationContext $context): ?EntryInterface
    {
        $contentType = self::entry($field)->contentType ?? EntryInterface::class;
        $entry = self::isUuid($value) ? $context->references->get(ReferenceType::ENTRY, strtolower($value)) : null;

        return $entry instanceof EntryInterface && $entry instanceof $contentType ? $entry : null;
    }

    public function fits(Field $field, mixed $value): bool
    {
        return self::isUuid($value);
    }

    public function references(Field $field, mixed $value): iterable
    {
        return self::isUuid($value) ? [new Reference(ReferenceType::ENTRY, strtolower($value), false)] : [];
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
        if (null === $value || self::isUuid($value)) {
            return;
        }

        $context->buildViolation('setono_sylius_cms.field.not_entry')->addViolation();
    }

    /**
     * @phpstan-assert-if-true string $value
     */
    private static function isUuid(mixed $value): bool
    {
        return is_string($value) && Uuid::isValid($value);
    }

    private static function entry(Field $field): Entry
    {
        if (!$field instanceof Entry) {
            throw new \InvalidArgumentException(sprintf('%s handles %s fields, got %s', self::class, Entry::class, $field::class));
        }

        return $field;
    }
}
