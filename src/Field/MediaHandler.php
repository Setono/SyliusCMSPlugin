<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Handles Media fields: a media item's UUID, hydrated as the media item from the references loaded before rendering.
 * A broken reference, or a media item of a kind the field doesn't take, hydrates as missing, so the field is empty
 */
final class MediaHandler implements FitCheckingFieldHandlerInterface
{
    public function schema(Field $field): array
    {
        return ['type' => 'string', 'format' => 'uuid', 'x-cms-options' => ['kinds' => self::media($field)->kinds]];
    }

    public function hydrate(Field $field, mixed $value, HydrationContext $context): ?MediaItemInterface
    {
        $field = self::media($field);

        return MediaItemReferences::isUuid($value) ? MediaItemReferences::resolve($field, $value, $context->references) : null;
    }

    public function fits(Field $field, mixed $value): bool
    {
        return MediaItemReferences::isUuid($value);
    }

    public function references(Field $field, mixed $value): iterable
    {
        return MediaItemReferences::isUuid($value) ? [MediaItemReferences::reference($value)] : [];
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
        if (null === $value || MediaItemReferences::isUuid($value)) {
            return;
        }

        $context->buildViolation('setono_sylius_cms.field.not_media_item')->addViolation();
    }

    private static function media(Field $field): Media
    {
        if (!$field instanceof Media) {
            throw new \InvalidArgumentException(sprintf('%s handles %s fields, got %s', self::class, Media::class, $field::class));
        }

        return $field;
    }
}
