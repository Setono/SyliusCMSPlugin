<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Handles Entries fields as every list field is handled, hand-picked or filled by a source, and names the code of the
 * content type the field names in its schema's x-cms-options, contentType, so the builder's picker searches only its
 * entries, such as a blog post's categories. A hand-picked entry of another content type isn't shown. See "Shipped field
 * types" in docs/spec/extending.md
 */
final readonly class EntriesHandler implements FitCheckingFieldHandlerInterface
{
    public function __construct(
        private ListFieldHandler $listFieldHandler,
        private ContentTypeRegistryInterface $contentTypes,
    ) {
    }

    public function schema(Field $field): array
    {
        $schema = $this->listFieldHandler->schema($field);

        $entries = self::entries($field);
        $contentType = $entries->contentType;

        /** @var array<string, mixed> $options */
        $options = is_array($schema['x-cms-options'] ?? null) ? $schema['x-cms-options'] : [];
        $options['contentType'] = null === $contentType ? null : $this->contentTypes->getByClass($contentType)->code;
        // The property whose collection's categories it lists, which the builder's picker searches
        if (null !== $entries->categoriesOf) {
            $options['categoriesOf'] = $entries->categoriesOf;
        }
        $schema['x-cms-options'] = $options;

        return $schema;
    }

    /**
     * @return ItemList<mixed>|null
     */
    public function hydrate(Field $field, mixed $value, HydrationContext $context): ?ItemList
    {
        return $this->listFieldHandler->hydrate(self::entries($field), $value, $context);
    }

    public function fits(Field $field, mixed $value): bool
    {
        return $this->listFieldHandler->fits(self::entries($field), $value);
    }

    public function references(Field $field, mixed $value): iterable
    {
        return $this->listFieldHandler->references(self::entries($field), $value);
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
        $this->listFieldHandler->validate(self::entries($field), $value, $context);
    }

    private static function entries(Field $field): Entries
    {
        if (!$field instanceof Entries) {
            throw new \InvalidArgumentException(sprintf('%s handles %s fields, got %s', self::class, Entries::class, $field::class));
        }

        return $field;
    }
}
