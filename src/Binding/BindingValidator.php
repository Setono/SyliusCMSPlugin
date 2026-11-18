<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Binding;

use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Registry\BindablePropertyRegistryInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What publish checks of a binding, whether it's a field's value, a Link's ref or a token in rich text: a property the
 * code has, of a type the field accepts, with its required parameters, each of which its field type takes. Whether the
 * entry provides the context the property needs is the context check's. See "Bindings" in docs/spec/slots.md
 *
 * @internal
 */
final readonly class BindingValidator
{
    public function __construct(
        private BindablePropertyRegistryInterface $properties,
        private FieldHandlerLocator $fieldHandlers,
        private FallbackValues $fallbackValues,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param StoredBinding $binding the property and its stored parameter values; its fallback is checked as the field's
     *                               value, by the field's own validation
     * @param list<BindingType> $accepts the binding types the field accepts
     */
    public function validate(StoredBinding $binding, array $accepts, ExecutionContextInterface $context): void
    {
        if (!$this->properties->has($binding->property)) {
            $context->buildViolation('setono_sylius_cms.field.unknown_binding')
                ->setParameter('{{ binding }}', $binding->property)
                ->addViolation()
            ;

            return;
        }

        $metadata = $this->properties->get($binding->property);
        if (!$metadata->fits($accepts)) {
            $context->buildViolation('setono_sylius_cms.field.binding_not_accepted')
                ->setParameter('{{ binding }}', $this->translator->trans($metadata->label))
                ->addViolation()
            ;

            return;
        }

        foreach ($metadata->fields as $name => $field) {
            $value = $binding->params[$name] ?? null;
            if ($this->fallbackValues->isMissing($field->field, $value)) {
                if ($field->field->required) {
                    $context->buildViolation('setono_sylius_cms.field.binding_parameter_required')
                        ->setParameter('{{ parameter }}', $this->translator->trans($field->field->label))
                        ->addViolation()
                    ;
                }

                continue;
            }

            $this->fieldHandlers->get($field->field)->validate($field->field, $value, $context);
        }
    }
}
