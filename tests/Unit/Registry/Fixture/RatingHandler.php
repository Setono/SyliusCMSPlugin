<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Registry\Fixture;

use Setono\SyliusCMSPlugin\Field\Field;
use Setono\SyliusCMSPlugin\Field\FieldHandlerInterface;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class RatingHandler implements FieldHandlerInterface
{
    public function schema(Field $field): array
    {
        return ['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'x-cms-options' => ['stars' => 5]];
    }

    public function hydrate(Field $field, mixed $value, HydrationContext $context): mixed
    {
        return $value;
    }

    public function references(Field $field, mixed $value): iterable
    {
        return [];
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
    }
}
