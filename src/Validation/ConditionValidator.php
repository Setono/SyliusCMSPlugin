<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation;

use Setono\SyliusCMSPlugin\Condition\StoredCondition;
use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Field\ChoiceHandler;
use Setono\SyliusCMSPlugin\Registry\ConditionKindMetadata;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Validates a block's visibility condition as publish does: it's in the stored condition format, of a kind the code
 * has, and its parameter values fit the kind. The parameters are checked as a block's shared fields are, each by its
 * field, then together against the constraints on the kind's parameters class, such as the date window's
 * EndsAfterStart. A channel, locale or customer group the store no longer has still fits a block's condition
 * (ChoiceHandler::keepingDeletedRecords()), as a deleted product does: it's a broken reference the admin flags
 * (BrokenConditionReference, DeletedCustomerGroup), not refused. A page scope's conditions get the same parameter
 * checks when a placement is saved (Slot\PageScopeValidator), which refuse one, as a block's field does, since nothing
 * flags it there. See "Validation" in docs/spec/publishing.md
 *
 * @internal
 */
final readonly class ConditionValidator
{
    public function __construct(
        private ConditionKindRegistryInterface $conditionKinds,
        private FieldValidator $fieldValidator,
        private ChoiceHandler $choiceHandler,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param mixed $value a block's condition as stored
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

        // A block's condition keeps a channel, locale or customer group the store no longer has
        $fields = array_map(fn (FieldMetadata $field): FieldMetadata => $this->keepingDeletedRecords($field, $condition->params[$field->name] ?? null), $kind->fields);

        return $this->validateFields($kind, $fields, $condition->params);
    }

    /**
     * The errors in the parameter values of a condition of the kind, checked on their own, as a page scope's are, so a
     * channel, locale or customer group the store no longer has is refused
     *
     * @param array<string, mixed> $params the stored parameter values, by name
     *
     * @return list<ValidationError> in no block: each at the parameter it's in, or at none for the parameters together
     */
    public function validateParams(ConditionKindMetadata $kind, array $params): array
    {
        return $this->validateFields($kind, $kind->fields, $params);
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

    /**
     * The parameter field as a block's condition holds it: a Choice of the store's records keeps the codes the value
     * names that the store no longer has among its choices
     */
    private function keepingDeletedRecords(FieldMetadata $field, mixed $value): FieldMetadata
    {
        if (!$field->field instanceof Choice) {
            return $field;
        }

        return new FieldMetadata($field->name, $this->choiceHandler->keepingDeletedRecords($field->field, $value), $field->type, $field->translatable, $field->default);
    }

    /**
     * @param array<string, FieldMetadata> $fields the kind's parameter fields, by name
     * @param array<string, mixed> $params
     *
     * @return list<ValidationError>
     */
    private function validateFields(ConditionKindMetadata $kind, array $fields, array $params): array
    {
        if (null === $kind->params) {
            return [];
        }

        // Parameter values are shared by all translations
        return $this->fieldValidator->validateFields($kind->params, $fields, $params, null);
    }
}
