<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Media\Fixture;

use Setono\SyliusCMSPlugin\Field\Field;
use Setono\SyliusCMSPlugin\Field\FieldHandlerInterface;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\Reference;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class LinkedHandler implements FieldHandlerInterface
{
    public function schema(Field $field): array
    {
        return ['type' => 'array'];
    }

    public function hydrate(Field $field, mixed $value, HydrationContext $context): mixed
    {
        return $value;
    }

    public function references(Field $field, mixed $value): iterable
    {
        if (!is_array($value)) {
            return;
        }

        foreach ($value as $link) {
            if (is_array($link) && is_string($link['type'] ?? null) && is_string($link['id'] ?? null)) {
                yield new Reference($link['type'], $link['id'], false);
            }
        }
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
    }
}
