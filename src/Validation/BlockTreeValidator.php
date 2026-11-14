<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Validation;

use Setono\SyliusCMSPlugin\Field\EmptinessCheckingFieldHandlerInterface;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Registry\AllowedBlockType;
use Setono\SyliusCMSPlugin\Registry\BlockTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Registry\NestingRulesInterface;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\StoredTree;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Validates a block tree as publish does. See "Validation" in docs/spec/publishing.md
 */
final readonly class BlockTreeValidator
{
    public function __construct(
        private BlockTypeRegistryInterface $blockTypes,
        private NestingRulesInterface $nestingRules,
        private FieldHandlerLocator $fieldHandlers,
        private FallbackChainInterface $fallbackChain,
        private ValidatorInterface $validator,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param string $contentType the code of the content type of the entry the tree is in
     * @param list<string> $locales the entry's locales, which translatable fields are validated in
     *
     * @return list<ValidationError>
     */
    public function validate(StoredTree $tree, string $contentType, array $locales): array
    {
        return $this->validateList($tree->blocks, $this->nestingRules->topLevel($contentType), null, $locales);
    }

    /**
     * Validates one list of blocks against what its spot may hold. Too few or too many blocks of a type is an error of
     * the block that holds the list, or of the entry at the top level
     *
     * @param list<StoredBlock> $blocks
     * @param list<AllowedBlockType> $allowed
     * @param string|null $parent the key of the block that holds the list, or null at the top level
     * @param list<string> $locales
     *
     * @return list<ValidationError>
     */
    private function validateList(array $blocks, array $allowed, ?string $parent, array $locales): array
    {
        $allowedCodes = array_map(static fn (AllowedBlockType $type): string => $type->code, $allowed);
        $errors = [];

        foreach ($blocks as $block) {
            // A block of a type the code no longer has is kept as it is, with what's inside it
            if (!$this->blockTypes->has($block->type)) {
                $errors[] = new ValidationError($block->key, null, null, $this->translator->trans('setono_sylius_cms.block.unknown_type', ['%type%' => $block->type], 'validators'));

                continue;
            }

            $blockType = $this->blockTypes->get($block->type);
            if (!in_array($block->type, $allowedCodes, true)) {
                $errors[] = new ValidationError($block->key, null, null, $this->translator->trans('setono_sylius_cms.block.not_allowed', ['%block%' => $this->label($blockType)], 'validators'));
            }

            foreach ($blockType->fields as $field) {
                $errors = [...$errors, ...$this->validateField($blockType, $field, $block, $locales)];
            }

            $errors = [...$errors, ...$this->validateList($block->children, $this->nestingRules->children($block->type), $block->key, $locales)];
        }

        foreach ($allowed as $type) {
            $count = count(array_filter($blocks, static fn (StoredBlock $block): bool => $block->type === $type->code));
            $parameters = ['%type%' => $this->label($this->blockTypes->get($type->code))];

            if (null !== $type->min && $count < $type->min) {
                $errors[] = new ValidationError($parent, null, null, $this->translator->trans('setono_sylius_cms.block.too_few', ['%count%' => $type->min] + $parameters, 'validators'));
            }

            if (null !== $type->max && $count > $type->max) {
                $errors[] = new ValidationError($parent, null, null, $this->translator->trans('setono_sylius_cms.block.too_many', ['%count%' => $type->max] + $parameters, 'validators'));
            }
        }

        return $errors;
    }

    private function label(BlockTypeMetadata $blockType): string
    {
        return $this->translator->trans($blockType->label);
    }

    /**
     * A shared field has one value. A translatable field is validated in each locale, with the value that renders
     * there: its own, or the one its fallback chain finds. Each error is reported in the locale whose value it is, once
     *
     * @param list<string> $locales
     *
     * @return list<ValidationError>
     */
    private function validateField(BlockTypeMetadata $blockType, FieldMetadata $field, StoredBlock $block, array $locales): array
    {
        $stored = $block->fields[$field->name] ?? null;

        if (!$field->translatable) {
            return array_map(
                static fn (string|\Stringable $message): ValidationError => new ValidationError($block->key, $field->name, null, $message),
                $this->validateValue($blockType, $field, $stored),
            );
        }

        $values = is_array($stored) ? $stored : [];

        $errors = [];
        $found = [];
        foreach ($locales as $locale) {
            [$source, $value] = $this->resolve($field, $values, $locale);
            foreach ($this->validateValue($blockType, $field, $value) as $message) {
                $error = new ValidationError($block->key, $field->name, $source, $message);

                // The same error can be found again through another locale's fallback chain
                if (!in_array([$source, $error->message], $found, true)) {
                    $found[] = [$source, $error->message];
                    $errors[] = $error;
                }
            }
        }

        return $errors;
    }

    /**
     * The value a translatable field renders in a locale, and the locale it's from: the first value the fallback
     * chain finds that isn't missing, or null in the locale itself
     *
     * @param array<array-key, mixed> $values locale => value
     *
     * @return array{string, mixed}
     */
    private function resolve(FieldMetadata $field, array $values, string $locale): array
    {
        foreach ($this->fallbackChain->localesFor($locale) as $candidate) {
            $value = $values[$candidate] ?? null;
            if (!$this->isMissing($field, $value)) {
                return [$candidate, $value];
            }
        }

        return [$locale, null];
    }

    /**
     * A missing value fails a required field, and otherwise goes on to the constraints as null. A value that's there
     * goes through the field type handler's validation first, and to the constraints only when the handler accepts it
     *
     * @return list<string|\Stringable> the messages
     */
    private function validateValue(BlockTypeMetadata $blockType, FieldMetadata $field, mixed $value): array
    {
        if ($this->isMissing($field, $value)) {
            if ($field->field->required) {
                return [$this->translator->trans('setono_sylius_cms.field.required', [], 'validators')];
            }

            $violations = $this->validator->validatePropertyValue($blockType->class, $field->name, null);
        } else {
            $handler = $this->fieldHandlers->get($field->field);
            $violations = $this->validator->validate($value, new Callback(
                static fn (mixed $value, ExecutionContextInterface $context) => $handler->validate($field->field, $value, $context),
            ));

            if (0 === count($violations)) {
                $violations = $this->validator->validatePropertyValue($blockType->class, $field->name, $value);
            }
        }

        return self::messages($violations);
    }

    /**
     * Null, '' and [] are missing, and so is what the field type handler counts as empty, such as an empty rich text document
     */
    private function isMissing(FieldMetadata $field, mixed $value): bool
    {
        if (null === $value || '' === $value || [] === $value) {
            return true;
        }

        $handler = $this->fieldHandlers->get($field->field);

        return $handler instanceof EmptinessCheckingFieldHandlerInterface && $handler->isEmpty($field->field, $value);
    }

    /**
     * @return list<string|\Stringable>
     */
    private static function messages(ConstraintViolationListInterface $violations): array
    {
        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = $violation->getMessage();
        }

        return $messages;
    }
}
