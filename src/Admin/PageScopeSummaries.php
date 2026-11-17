<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Admin;

use Psr\Container\ContainerInterface;
use Setono\SyliusCMSPlugin\Builder\Search\SearchInterface;
use Setono\SyliusCMSPlugin\Condition\StoredCondition;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Model\PlacementInterface;
use Setono\SyliusCMSPlugin\Registry\ConditionKindMetadata;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistryInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Words placements' page scopes for the Placements section of a snippet's overview page. What their parameters refer
 * to, such as a product condition's products, is named through the builder's searches, which look each kind of
 * reference up once for all of them. See "Placements" in docs/spec/admin.md
 *
 * @internal
 */
final readonly class PageScopeSummaries
{
    /**
     * @param ContainerInterface $searches the builder's searches (SearchInterface), by the kind they search, such as
     *                                     product
     */
    public function __construct(
        private ConditionKindRegistryInterface $conditionKinds,
        private FieldHandlerLocator $fieldHandlers,
        private ContainerInterface $searches,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param list<PlacementInterface> $placements
     *
     * @return array<string, list<PageScopeCondition>> by placement UUID, its conditions in order
     */
    public function of(array $placements): array
    {
        /** @var array<string, list<array{string, ConditionKindMetadata|null, bool, list<array{string, string}|string>}>> $read by placement UUID */
        $read = [];
        /** @var array<string, array<string, true>> $refs type => id => true */
        $refs = [];

        foreach ($placements as $placement) {
            $read[$placement->getUuid()] = [];
            foreach ($placement->getPageScope() as $value) {
                $condition = StoredCondition::fromValue($value);
                if (null === $condition) {
                    continue;
                }

                $kind = $this->conditionKinds->has($condition['kind']) ? $this->conditionKinds->get($condition['kind']) : null;
                $values = null === $kind ? [] : $this->values($kind, $condition['params']);
                foreach ($values as $named) {
                    if (is_array($named)) {
                        $refs[$named[0]][$named[1]] = true;
                    }
                }

                $read[$placement->getUuid()][] = [$condition['kind'], $kind, $condition['negate'], $values];
            }
        }

        $names = $this->names($refs);

        return array_map(fn (array $conditions): array => array_map(fn (array $condition): PageScopeCondition => new PageScopeCondition(
            $condition[0],
            null === $condition[1] ? null : $this->translator->trans($condition[1]->label),
            null === $condition[1] ? null : $this->translator->trans($condition[2] ? $condition[1]->matchesNot : $condition[1]->matches),
            array_map(static fn (array|string $named): string => is_array($named) ? ($names[$named[0]][$named[1]] ?? $named[1]) : $named, $condition[3]),
        ), $conditions), $read);
    }

    /**
     * What a condition's parameters hold, in the order of its kind's fields: a reference as [type, id], to be named,
     * and anything else as the text it shows as
     *
     * @param array<string, mixed> $params
     *
     * @return list<array{string, string}|string>
     */
    private function values(ConditionKindMetadata $kind, array $params): array
    {
        $values = [];
        foreach ($kind->fields as $name => $field) {
            $value = $params[$name] ?? null;
            if (null === $value) {
                continue;
            }

            $references = [...$this->fieldHandlers->get($field->field)->references($field->field, $value)];
            if ([] !== $references) {
                foreach ($references as $reference) {
                    $values[] = [$reference->type, $reference->id];
                }

                continue;
            }

            if (true === $value) {
                $values[] = $this->translator->trans($field->field->label);
            } elseif (is_string($value) || is_int($value) || is_float($value)) {
                $values[] = (string) $value;
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    if (is_string($item) || is_int($item) || is_float($item)) {
                        $values[] = (string) $item;
                    }
                }
            }
        }

        return $values;
    }

    /**
     * @param array<string, array<string, true>> $refs type => id => true
     *
     * @return array<string, array<string, string>> type => id => name, of those that exist
     */
    private function names(array $refs): array
    {
        $localeCode = $this->translator instanceof LocaleAwareInterface ? $this->translator->getLocale() : 'en';

        $names = [];
        foreach ($refs as $type => $ids) {
            if (!$this->searches->has($type)) {
                continue;
            }

            /** @var SearchInterface $search */
            $search = $this->searches->get($type);
            foreach ($search->find(array_map(strval(...), array_keys($ids)), $localeCode) as $result) {
                $names[$type][$result->ref] = $result->name;
            }
        }

        return $names;
    }
}
