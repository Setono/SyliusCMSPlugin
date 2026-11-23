<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation;

use Setono\SyliusCMSPlugin\Condition\StoredCondition;
use Setono\SyliusCMSPlugin\Registry\ConditionKindMetadata;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Validates a visibility condition as publish does: it's in the stored condition format, of a kind the code has, and
 * its parameter values fit the kind. The parameters are checked as a block's shared fields are, each by its field, then
 * together against the constraints on the kind's parameters class, such as the date window's EndsAfterStart. A
 * channel, locale or customer group the store no longer has still fits (Field\RecordChoicesProviderInterface), as a
 * deleted product does: it's a broken reference the admin flags, not refused. A page scope's conditions get the same
 * parameter checks when a placement is saved (Slot\PageScopeValidator). See "Validation" in docs/spec/publishing.md
 *
 * @internal
 */
final readonly class ConditionValidator
{
    public function __construct(
        private ConditionKindRegistryInterface $conditionKinds,
        private FieldValidator $fieldValidator,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param mixed $value the condition as stored
     *
     * @return list<ValidationError> in no block: each at the parameter it's in, or at none for the condition as a whole
     */
    public function validate(mixed $value): array
    {
        $condition = StoredCondition::fromValue($value);
        if (null === $condition) {
            return [new ValidationError(null, null, null, $this->translator->trans('setono_sylius_cms.condition.malformed', [], 'validators'))];
        }

        $kind = $this->conditionKinds->find($condition->kind);
        if (null === $kind) {
            return [new ValidationError(null, null, null, $this->translator->trans('setono_sylius_cms.condition.unknown_kind', ['%kind%' => $condition->kind], 'validators'))];
        }

        return $this->validateParams($kind, $condition->params);
    }

    /**
     * The errors in the parameter values of a condition of the kind
     *
     * @param array<string, mixed> $params the stored parameter values, by name
     *
     * @return list<ValidationError> in no block: each at the parameter it's in, or at none for the parameters together
     */
    public function validateParams(ConditionKindMetadata $kind, array $params): array
    {
        if (null === $kind->params) {
            return [];
        }

        // Parameter values are shared by all translations
        return $this->fieldValidator->validateFields($kind->params, $kind->fields, $params, null);
    }

    /**
     * The label of the parameter an error validateParams() found for a condition of the kind is at, a translation key,
     * or null for an error at none, about the parameters together, such as a constraint on the parameters class whose
     * violation has no property path
     */
    public function parameterLabel(ConditionKindMetadata $kind, ValidationError $error): ?string
    {
        return null === $error->field ? null : ($kind->fields[$error->field] ?? null)?->field->label;
    }
}
