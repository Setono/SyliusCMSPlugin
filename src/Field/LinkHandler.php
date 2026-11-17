<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Link\LinkResolver;
use Setono\SyliusCMSPlugin\Localization\FallbackChainInterface;
use Setono\SyliusCMSPlugin\Registry\LinkTargetMetadata;
use Setono\SyliusCMSPlugin\Registry\LinkTargetRegistryInterface;
use Setono\SyliusCMSPlugin\Value\Link as LinkValue;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Handles Link fields: {target, ref, anchor, label} in one of three shapes, which the field's own translatable picks
 * (null, false or true). Hydration picks the current locale's ref and label along the fallback chain, so a link
 * target's resolve() always gets one string, and resolves the link. A link to a record, such as a product, refers to
 * it, so it's loaded before rendering with the tree's other references. See "Links" in docs/spec/content-model.md
 */
final readonly class LinkHandler implements FitCheckingFieldHandlerInterface, EmptinessCheckingFieldHandlerInterface
{
    public function __construct(
        private LinkTargetRegistryInterface $linkTargets,
        private LinkResolver $linkResolver,
        private FallbackChainInterface $fallbackChain,
    ) {
    }

    public function schema(Field $field): array
    {
        $field = self::link($field);
        $targets = $this->targets($field);
        $perLocale = self::perLocale($field);
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
        $value = self::normalized($value);
        if (!$this->fits($field, $value) || !in_array($value['target'], $this->targets($field), true)) {
            return null;
        }

        $locale = $context->renderContext->localeCode;
        $ref = $this->localized($value['ref'], $locale);
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
     * @phpstan-assert-if-true array{target: string, ref: string|array<string, string>, anchor?: string|null, label?: string|array<string, string>|null} $value
     */
    public function fits(Field $field, mixed $value): bool
    {
        $value = self::normalized($value);
        if (!is_array($value) || !is_string($value['target'] ?? null) || !array_key_exists('ref', $value)) {
            return false;
        }

        $anchor = $value['anchor'] ?? null;
        if (null !== $anchor && !is_string($anchor)) {
            return false;
        }

        $label = $value['label'] ?? null;
        if (!self::perLocale(self::link($field))) {
            return is_string($value['ref']) && (null === $label || is_string($label));
        }

        return (is_string($value['ref']) || self::isLocaleMap($value['ref'])) && (null === $label || self::isLocaleMap($label));
    }

    public function isEmpty(Field $field, mixed $value): bool
    {
        $value = self::normalized($value);
        if (!is_array($value)) {
            return false;
        }

        $ref = $value['ref'] ?? null;

        return is_array($ref) ? self::isLocaleMap($ref) && [] === self::refs($ref) : null === $ref || '' === $ref;
    }

    public function references(Field $field, mixed $value): iterable
    {
        $value = self::normalized($value);
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
        $value = self::normalized($value);
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

        foreach (self::refs($value['ref']) as $ref) {
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
        if (is_array($ref) && !self::isLocaleMap($ref)) {
            return [];
        }

        $refs = is_array($ref) ? $ref : [$ref];

        return array_values(array_filter($refs, static fn (mixed $ref): bool => is_string($ref) && '' !== $ref));
    }

    /**
     * The stored link with its locale maps as arrays: canonical JSON decodes an empty map, such as a label in no locale,
     * as an empty stdClass
     */
    private static function normalized(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        foreach (['ref', 'label'] as $key) {
            if (($value[$key] ?? null) instanceof \stdClass) {
                $value[$key] = get_object_vars($value[$key]);
            }
        }

        return $value;
    }

    /**
     * Whether the label, and the ref of a target whose ref is translatable, are kept per locale inside the one value,
     * which is the shape of a Link field whose translatable is null
     */
    private static function perLocale(Link $field): bool
    {
        return null === $field->translatable;
    }

    /**
     * @phpstan-assert-if-true array<string, string> $value
     */
    private static function isLocaleMap(mixed $value): bool
    {
        if (!is_array($value) || ([] !== $value && array_is_list($value))) {
            return false;
        }

        foreach ($value as $locale => $localized) {
            if (!is_string($locale) || str_starts_with($locale, '$') || !is_string($localized)) {
                return false;
            }
        }

        return true;
    }

    private static function link(Field $field): Link
    {
        if (!$field instanceof Link) {
            throw new \InvalidArgumentException(sprintf('%s handles %s fields, got %s', self::class, Link::class, $field::class));
        }

        return $field;
    }
}
