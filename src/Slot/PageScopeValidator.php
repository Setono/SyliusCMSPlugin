<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Slot;

use Setono\SyliusCMSPlugin\Condition\StoredCondition;
use Setono\SyliusCMSPlugin\Registry\ConditionKindMetadata;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ContextKeyRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Registry\SlotRegistryInterface;
use Setono\SyliusCMSPlugin\Validation\ConditionValidator;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Holds a page scope to the slot it's for. Its conditions name what's wrong by their number, from 1, and their kind's
 * label. Parameter values are checked as publish checks a block's visibility conditions (ConditionValidator): by their
 * fields, so a required parameter, such as a product condition's products, must have a value, and together, against
 * the constraints on the kind's parameters class
 *
 * @internal
 */
final readonly class PageScopeValidator implements PageScopeValidatorInterface
{
    private const DOMAIN = 'validators';

    public function __construct(
        private ConditionKindRegistryInterface $conditionKinds,
        private SlotRegistryInterface $slots,
        private ContextKeyRegistryInterface $contextKeys,
        private ConditionValidator $conditionValidator,
    ) {
    }

    public function validate(string $slot, array $pageScope): array
    {
        if ([] === $pageScope) {
            return [];
        }

        if (!$this->slots->has($slot)) {
            return [new TranslatableMessage('setono_sylius_cms.page_scope.removed_slot', ['%slot%' => $slot], self::DOMAIN)];
        }

        $errors = [];
        foreach (array_values($pageScope) as $index => $value) {
            $errors = [...$errors, ...$this->validateCondition($this->slots->get($slot), $index + 1, $value)];
        }

        return $errors;
    }

    /**
     * @return list<TranslatableMessage>
     */
    private function validateCondition(SlotMetadata $slot, int $number, mixed $value): array
    {
        $condition = StoredCondition::fromValue($value);
        if (null === $condition) {
            return [new TranslatableMessage('setono_sylius_cms.page_scope.malformed', ['%number%' => $number], self::DOMAIN)];
        }

        $kind = $this->conditionKinds->find($condition->kind);
        if (null === $kind) {
            return [new TranslatableMessage('setono_sylius_cms.page_scope.unknown_kind', ['%number%' => $number, '%kind%' => $condition->kind], self::DOMAIN)];
        }

        $label = new TranslatableMessage($kind->label);
        if (!$kind->readsHostPage()) {
            return [new TranslatableMessage('setono_sylius_cms.page_scope.not_host_page', ['%number%' => $number, '%kind%' => $label], self::DOMAIN)];
        }

        if (!$slot->canScopeWith($kind)) {
            return array_map(fn (string $key): TranslatableMessage => new TranslatableMessage('setono_sylius_cms.page_scope.needs', [
                '%number%' => $number,
                '%kind%' => $label,
                '%context%' => $this->contextKeys->labelOf($key),
            ], self::DOMAIN), $slot->unprovided($kind->needs));
        }

        return $this->validateParams($kind, $number, $label, $condition->params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return list<TranslatableMessage>
     */
    private function validateParams(ConditionKindMetadata $kind, int $number, TranslatableMessage $label, array $params): array
    {
        $errors = [];
        foreach ($this->conditionValidator->validateParams($kind, $params) as $error) {
            $field = null === $error->field ? null : ($kind->fields[$error->field] ?? null);
            $errors[] = null === $field
                ? new TranslatableMessage('setono_sylius_cms.page_scope.condition', ['%number%' => $number, '%kind%' => $label, '%message%' => $error->message], self::DOMAIN)
                : new TranslatableMessage('setono_sylius_cms.page_scope.field', [
                    '%number%' => $number,
                    '%kind%' => $label,
                    '%field%' => new TranslatableMessage($field->field->label),
                    '%message%' => $error->message,
                ], self::DOMAIN);
        }

        return $errors;
    }
}
