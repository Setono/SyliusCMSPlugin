<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Handles Taxon fields: a taxon's code, hydrated as the taxon from the references loaded before rendering, which hold
 * only the taxons the shopper may see. One the shopper may not see, or that was deleted, hydrates as missing
 */
final class TaxonHandler implements FitCheckingFieldHandlerInterface
{
    public function schema(Field $field): array
    {
        return ['type' => 'string', 'minLength' => 1];
    }

    public function hydrate(Field $field, mixed $value, HydrationContext $context): ?TaxonInterface
    {
        $taxon = Codes::isCode($value) ? $context->references->get('taxon', $value) : null;

        return $taxon instanceof TaxonInterface ? $taxon : null;
    }

    public function fits(Field $field, mixed $value): bool
    {
        return Codes::isCode($value);
    }

    public function references(Field $field, mixed $value): iterable
    {
        return Codes::references('taxon', [$value]);
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
        if (null === $value || Codes::isCode($value)) {
            return;
        }

        $context->buildViolation('setono_sylius_cms.field.not_taxon')->addViolation();
    }
}
