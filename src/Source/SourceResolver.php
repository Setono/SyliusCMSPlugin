<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Source;

use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Setono\SyliusCMSPlugin\Field\ListField;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Localization\FallbackValues;
use Setono\SyliusCMSPlugin\Registry\SourceMetadata;
use Setono\SyliusCMSPlugin\Registry\SourceRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\ParamsHydrator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Resolves what list fields store when a source fills them: the references the source's parameters hold, which load
 * with the rest of the tree's before rendering, the source's adapter with its parameters hydrated, and what publish
 * checks. A source the code no longer has, one that lists something else than the field, or one that needs a context
 * key the page doesn't have, yields nothing. A disabled source keeps resolving. See "The contract" in
 * docs/spec/sources.md
 *
 * @internal
 */
final readonly class SourceResolver
{
    public function __construct(
        private SourceRegistryInterface $sources,
        private ParamsHydrator $paramsHydrator,
        private FieldHandlerLocator $fieldHandlers,
        private FallbackValues $fallbackValues,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * The references its parameter values hold, or none for a source the code no longer has
     *
     * @param array<array-key, mixed> $params the stored parameter values, by field name
     *
     * @return list<Reference>
     */
    public function references(string $code, array $params): array
    {
        return $this->sources->has($code) ? $this->paramsHydrator->references($this->sources->get($code)->fields, $params) : [];
    }

    /**
     * The source's adapter, with its parameters hydrated from the references in the context, or an empty one when it
     * yields nothing
     *
     * @param array<array-key, mixed> $params the stored parameter values, by field name
     * @param class-string|null $of what the list lists, such as ProductInterface::class, or null for anything
     *
     * @return AdapterInterface<mixed>
     */
    public function resolve(string $code, array $params, ?string $of, HydrationContext $context): AdapterInterface
    {
        if (!$this->sources->has($code)) {
            return new ArrayAdapter([]);
        }

        $source = $this->sources->get($code);
        if (null !== $of && !$source->lists($of)) {
            return new ArrayAdapter([]);
        }

        foreach ($source->needs as $key) {
            if (null === $context->renderContext->get($key)) {
                return new ArrayAdapter([]);
            }
        }

        return $source->source->resolve($this->paramsHydrator->hydrate($source->params, $source->fields, $params, $context), $context->renderContext);
    }

    /**
     * Adds a violation for a source the code no longer has, one the field's sources option doesn't allow, one that lists
     * something else than the field, a required parameter that's missing, and a parameter its field type refuses. A
     * disabled source is fine
     *
     * @param array<array-key, mixed> $params the stored parameter values, by field name
     */
    public function validate(string $code, array $params, ListField $field, ExecutionContextInterface $context): void
    {
        if (!$this->sources->has($code)) {
            $context->buildViolation('setono_sylius_cms.field.unknown_source')
                ->setParameter('{{ source }}', $code)
                ->addViolation()
            ;

            return;
        }

        $source = $this->sources->get($code);
        if (!$field->allows($code)) {
            $context->buildViolation('setono_sylius_cms.field.source_not_allowed')
                ->setParameter('{{ source }}', $this->translator->trans($source->label))
                ->addViolation()
            ;

            return;
        }

        if (!$source->lists($field->itemClass())) {
            $context->buildViolation('setono_sylius_cms.field.source_of_another_kind')
                ->setParameter('{{ source }}', $this->translator->trans($source->label))
                ->addViolation()
            ;

            return;
        }

        $this->validateParams($source, $params, $context);
    }

    /**
     * @param array<array-key, mixed> $params
     */
    private function validateParams(SourceMetadata $source, array $params, ExecutionContextInterface $context): void
    {
        foreach ($source->fields as $name => $field) {
            $value = $params[$name] ?? null;
            if ($this->fallbackValues->isMissing($field->field, $value)) {
                if ($field->field->required) {
                    $context->buildViolation('setono_sylius_cms.field.source_parameter_required')
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
