<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles Choice fields: one of the fixed choices, or of the choices its provider gives, or a list of them when multiple.
 * A provider of the store's records gives codes, which hold references (RecordChoicesProviderInterface)
 */
final readonly class ChoiceHandler implements FitCheckingFieldHandlerInterface
{
    /**
     * @param ContainerInterface $choicesProviders the choices providers, by class
     */
    public function __construct(
        private TranslatorInterface $translator,
        private ContainerInterface $choicesProviders = new ServiceLocator([]),
    ) {
    }

    /**
     * The choices in the translator's locale, which in the admin is the admin user's locale
     */
    public function schema(Field $field): array
    {
        $field = self::choice($field);
        $choices = $this->choices($field, $this->locale());

        $one = ['type' => 'string', 'enum' => array_map(strval(...), array_keys($choices))];
        $schema = $field->multiple ? ['type' => 'array', 'items' => $one, 'uniqueItems' => true] : $one;
        $schema['x-cms-options'] = ['choices' => $choices, 'multiple' => $field->multiple];

        return $schema;
    }

    /**
     * @return string|list<string>|null
     */
    public function hydrate(Field $field, mixed $value, HydrationContext $context): string|array|null
    {
        $field = self::choice($field);
        $values = $this->values($field, $context->renderContext->localeCode);

        if ($field->multiple) {
            return is_array($value) ? array_values(array_filter($value, static fn (mixed $item): bool => in_array($item, $values, true))) : [];
        }

        return in_array($value, $values, true) ? $value : null;
    }

    public function references(Field $field, mixed $value): iterable
    {
        return [];
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
        $field = self::choice($field);

        if (null === $value || $this->fits($field, $value)) {
            return;
        }

        $context->buildViolation('setono_sylius_cms.field.invalid_choice')->addViolation();
    }

    /**
     * One of the choices the field still offers, or for a multiple choice a list of them. Choices of the store's records
     * (RecordChoicesProviderInterface) are codes: one the store no longer has is a broken reference, not a value that
     * doesn't fit
     */
    public function fits(Field $field, mixed $value): bool
    {
        $field = self::choice($field);
        $fitsOne = $this->holdsRecords($field) ? Codes::isCode(...) : fn (mixed $item): bool => in_array($item, $this->values($field, $this->locale()), true);

        if (!$field->multiple) {
            return $fitsOne($value);
        }

        if (!is_array($value) || !array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!$fitsOne($item)) {
                return false;
            }
        }

        return true;
    }

    private function holdsRecords(Choice $field): bool
    {
        return null !== $field->choicesProvider && $this->provider($field->choicesProvider) instanceof RecordChoicesProviderInterface;
    }

    /**
     * @return list<string>
     */
    private function values(Choice $field, string $localeCode): array
    {
        return null === $field->choicesProvider ? $field->values() : array_map(strval(...), array_keys($this->provider($field->choicesProvider)->getChoices($localeCode)));
    }

    /**
     * Value => label: the fixed choices with their labels translated, or the provider's choices in the locale
     *
     * @return array<array-key, string>
     */
    private function choices(Choice $field, string $localeCode): array
    {
        if (null !== $field->choicesProvider) {
            return $this->provider($field->choicesProvider)->getChoices($localeCode);
        }

        return array_map(fn (string $label): string => $this->translator->trans($label), $field->choices);
    }

    private function provider(string $class): ChoicesProviderInterface
    {
        if (!$this->choicesProviders->has($class)) {
            throw new \LogicException(sprintf('The choices provider %s isn\'t registered. Make it a service implementing %s, whose id is its class, and autoconfigure it', $class, ChoicesProviderInterface::class));
        }

        $provider = $this->choicesProviders->get($class);
        if (!$provider instanceof ChoicesProviderInterface) {
            throw new \LogicException(sprintf('The choices provider %s must implement %s', $class, ChoicesProviderInterface::class));
        }

        return $provider;
    }

    /**
     * The translator's locale, which in the admin is the admin user's. Symfony's translator always knows it
     */
    private function locale(): string
    {
        return $this->translator instanceof LocaleAwareInterface ? $this->translator->getLocale() : 'en';
    }

    private static function choice(Field $field): Choice
    {
        if (!$field instanceof Choice) {
            throw new \InvalidArgumentException(sprintf('%s handles %s fields, got %s', self::class, Choice::class, $field::class));
        }

        return $field;
    }
}
