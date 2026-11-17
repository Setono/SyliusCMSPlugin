<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Value\ItemList;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Handles Media list fields: an array of media item UUIDs, hydrated as a list of the media items from the references
 * loaded before rendering, in their order. A broken reference, or a media item of a kind the field doesn't take, is left
 * out of the list
 */
final class MediaListHandler implements FitCheckingFieldHandlerInterface
{
    public function schema(Field $field): array
    {
        $field = self::mediaList($field);

        $schema = ['type' => 'array', 'items' => ['type' => 'string', 'format' => 'uuid']];
        if (null !== $field->max) {
            $schema['maxItems'] = $field->max;
        }

        $schema['x-cms-options'] = ['kinds' => $field->kinds, 'max' => $field->max];

        return $schema;
    }

    /**
     * @return ItemList<MediaItemInterface>|null
     */
    public function hydrate(Field $field, mixed $value, HydrationContext $context): ?ItemList
    {
        $field = self::mediaList($field);
        if (!self::isUuidList($value)) {
            return null;
        }

        $mediaItems = [];
        foreach ($value as $uuid) {
            $mediaItem = MediaItemReferences::resolve($field, $uuid, $context->references);
            if (null !== $mediaItem) {
                $mediaItems[] = $mediaItem;
            }
        }

        return ItemList::of($mediaItems);
    }

    /**
     * A list of UUIDs fits, even one longer than the most, which still renders and which publish refuses
     */
    public function fits(Field $field, mixed $value): bool
    {
        return self::isUuidList($value);
    }

    public function references(Field $field, mixed $value): iterable
    {
        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        $references = [];
        foreach ($value as $uuid) {
            if (MediaItemReferences::isUuid($uuid)) {
                $reference = MediaItemReferences::reference($uuid);
                $references[$reference->id] ??= $reference;
            }
        }

        return array_values($references);
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
        $field = self::mediaList($field);

        if (null === $value) {
            return;
        }

        if (!self::isUuidList($value)) {
            $context->buildViolation('setono_sylius_cms.field.not_media_list')->addViolation();

            return;
        }

        if (null !== $field->max && count($value) > $field->max) {
            $context->buildViolation('setono_sylius_cms.field.too_many_media_items')
                ->setParameter('{{ limit }}', (string) $field->max)
                ->setPlural($field->max)
                ->addViolation()
            ;
        }
    }

    /**
     * @phpstan-assert-if-true list<string> $value
     */
    private static function isUuidList(mixed $value): bool
    {
        if (!is_array($value) || !array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!MediaItemReferences::isUuid($item)) {
                return false;
            }
        }

        return true;
    }

    private static function mediaList(Field $field): MediaList
    {
        if (!$field instanceof MediaList) {
            throw new \InvalidArgumentException(sprintf('%s handles %s fields, got %s', self::class, MediaList::class, $field::class));
        }

        return $field;
    }
}
