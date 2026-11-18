<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Binding\BindingResolver;
use Setono\SyliusCMSPlugin\Binding\BindingType;
use Setono\SyliusCMSPlugin\Binding\BindingValidator;
use Setono\SyliusCMSPlugin\Binding\StoredBinding;
use Setono\SyliusCMSPlugin\Link\LinkResolver;
use Setono\SyliusCMSPlugin\Link\Target\UrlLinkTarget;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Registry\LinkTargetMetadata;
use Setono\SyliusCMSPlugin\Registry\LinkTargetRegistryInterface;
use Setono\SyliusCMSPlugin\Value\Link as LinkValue;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Handles Link fields: {target, ref, anchor, label} in one of three shapes, which the field's own translatable picks
 * (null, false or true). Hydration picks the current locale's ref and label along the fallback chain, so a link
 * target's resolve() always gets one string, and resolves the link. A link to a record, such as a product, refers to
 * it, so it's loaded before rendering with the tree's other references. A link to a URL can hold a binding of type url
 * in its ref, such as the host page's product's URL, and keeps its own label and anchor: its fallback is what the ref
 * would otherwise store. See "Links" in docs/spec/content-model.md
 */
final readonly class LinkHandler implements FitCheckingFieldHandlerInterface, EmptinessCheckingFieldHandlerInterface, InnerBindingFieldHandlerInterface
{
    public function __construct(
        private LinkTargetRegistryInterface $linkTargets,
        private LinkResolver $linkResolver,
        private FallbackChainInterface $fallbackChain,
        private BindingResolver $bindings,
        private BindingValidator $bindingValidator,
    ) {
    }

    public function schema(Field $field): array
    {
        $field = self::link($field);
        $targets = $this->targets($field);
        $perLocale = LinkLocaleParts::areKeptBy($field);
        $localeMap = ['type' => 'object', 'additionalProperties' => ['type' => 'string']];

        return [
            'type' => 'object',
            'properties' => [
                'target' => ['type' => 'string', 'enum' => $targets],
                'ref' => $perLocale ? ['oneOf' => [['type' => 'string'], $localeMap]] : ['type' => 'string'],
                'anchor' => ['type' => ['string', 'null']],
                'label' => $perLocale ? $localeMap : ['type' => ['string', 'null']],
            ],
            'required' => ['target', 'ref'],
            'x-cms-options' => ['targets' => $targets, 'translatable' => $field->translatable],
        ];
    }

    public function hydrate(Field $field, mixed $value, HydrationContext $context): ?LinkValue
    {
        $field = self::link($field);
        $value = LinkLocaleParts::normalized($value);
        if (!$this->fits($field, $value) || !in_array($value['target'], $this->targets($field), true)) {
            return null;
        }

        $locale = $context->renderContext->localeCode;
        $binding = StoredBinding::fromValue($value['ref']);
        $ref = null === $binding ? $this->localized($value['ref'], $locale) : $this->bound($binding, $context);
        if (null === $ref) {
            return null;
        }

        $anchor = $value['anchor'] ?? null;
        $anchor = '' === $anchor ? null : $anchor;

        return new LinkValue(
            $value['target'],
            $ref,
            $anchor,
            $this->localized($value['label'] ?? null, $locale),
            $this->linkResolver->resolve($value['target'], $ref, $anchor, $context),
        );
    }

    /**
     * @phpstan-assert-if-true array{target: string, ref: string|array<string, mixed>, anchor?: string|null, label?: string|array<string, string>|null} $value
     */
    public function fits(Field $field, mixed $value): bool
    {
        $value = LinkLocaleParts::normalized($value);
        if (!is_array($value) || !is_string($value['target'] ?? null) || !array_key_exists('ref', $value)) {
            return false;
        }

        $anchor = $value['anchor'] ?? null;
        if (null !== $anchor && !is_string($anchor)) {
            return false;
        }

        $label = $value['label'] ?? null;
        $ref = $value['ref'];
        if (StoredBinding::isBinding($ref)) {
            // A binding is only a URL's, and its fallback is what the ref would otherwise store
            $binding = StoredBinding::fromValue($ref);
            if (null === $binding || UrlLinkTarget::CODE !== $value['target']) {
                return false;
            }

            $ref = $binding->fallback ?? '';
        }

        if (!LinkLocaleParts::areKeptBy($field)) {
            return is_string($ref) && (null === $label || is_string($label));
        }

        return (is_string($ref) || LinkLocaleParts::isLocaleMap($ref)) && (null === $label || LinkLocaleParts::isLocaleMap($label));
    }

    public function isEmpty(Field $field, mixed $value): bool
    {
        $value = LinkLocaleParts::normalized($value);
        if (!is_array($value)) {
            return false;
        }

        $ref = $value['ref'] ?? null;

        return is_array($ref) ? LinkLocaleParts::isLocaleMap($ref) && [] === self::refs($ref) : null === $ref || '' === $ref;
    }

    /**
     * The binding a link to a URL holds in its ref
     */
    public function bindings(Field $field, mixed $value): iterable
    {
        $value = LinkLocaleParts::normalized($value);
        $binding = is_array($value) ? StoredBinding::fromValue($value['ref'] ?? null) : null;

        return null === $binding ? [] : [$binding];
    }

    public function references(Field $field, mixed $value): iterable
    {
        $value = LinkLocaleParts::normalized($value);
        if (!is_array($value) || !is_string($value['target'] ?? null)) {
            return [];
        }

        $references = [];
        foreach (self::refs($value['ref'] ?? null) as $ref) {
            $reference = $this->linkResolver->reference($value['target'], $ref);
            if (null !== $reference) {
                $references[$reference->id] = $reference;
            }
        }

        return array_values($references);
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
        $field = self::link($field);
        $value = LinkLocaleParts::normalized($value);
        if (null === $value) {
            return;
        }

        if (!$this->fits($field, $value)) {
            $context->buildViolation('setono_sylius_cms.field.not_link')->addViolation();

            return;
        }

        if (!in_array($value['target'], $this->targets($field), true)) {
            $context->buildViolation('setono_sylius_cms.field.link_target_not_allowed')
                ->setParameter('%target%', $value['target'])
                ->addViolation()
            ;

            return;
        }

        $binding = StoredBinding::fromValue($value['ref']);
        if (null !== $binding) {
            $this->bindingValidator->validate($binding, [BindingType::Url], $context);
        }

        foreach (self::refs($binding->fallback ?? $value['ref']) as $ref) {
            $error = $this->linkResolver->refError($value['target'], $ref);
            if (null !== $error) {
                $context->buildViolation($error)->addViolation();

                return;
            }
        }

        $anchor = $value['anchor'] ?? null;
        if (null !== $anchor && 1 === preg_match('/[#\s]/', $anchor)) {
            $context->buildViolation('setono_sylius_cms.field.not_link_anchor')->addViolation();
        }
    }

    /**
     * The codes of the link targets the field links to that exist, in the field's order
     *
     * @return list<string>
     */
    private function targets(Link $field): array
    {
        $all = array_map(static fn (LinkTargetMetadata $target): string => $target->code, $this->linkTargets->all());

        return null === $field->targets ? $all : array_values(array_filter($field->targets, $this->linkTargets->has(...)));
    }

    /**
     * The URL a bound ref resolves to, or else its fallback in the current locale, or null when both are empty
     */
    private function bound(StoredBinding $binding, HydrationContext $context): ?string
    {
        $url = $this->bindings->resolve($binding, [BindingType::Url], $context);

        return is_string($url) ? $url : $this->localized($binding->fallback, $context->renderContext->localeCode);
    }

    /**
     * A string as it is, or of a locale map the first string that isn't empty along the locale's fallback chain. Null
     * when there's none
     */
    private function localized(mixed $value, string $localeCode): ?string
    {
        if (!is_array($value)) {
            return is_string($value) && '' !== $value ? $value : null;
        }

        foreach ($this->fallbackChain->localesFor($localeCode) as $locale) {
            $localized = $value[$locale] ?? null;
            if (is_string($localized) && '' !== $localized) {
                return $localized;
            }
        }

        return null;
    }

    /**
     * The refs a stored ref holds that aren't empty: itself, or each locale's. A special value, such as a binding,
     * holds none
     *
     * @return list<string>
     */
    private static function refs(mixed $ref): array
    {
        if (is_array($ref) && !LinkLocaleParts::isLocaleMap($ref)) {
            return [];
        }

        $refs = is_array($ref) ? $ref : [$ref];

        return array_values(array_filter($refs, static fn (mixed $ref): bool => is_string($ref) && '' !== $ref));
    }

    private static function link(Field $field): Link
    {
        if (!$field instanceof Link) {
            throw new \InvalidArgumentException(sprintf('%s handles %s fields, got %s', self::class, Link::class, $field::class));
        }

        return $field;
    }
}
