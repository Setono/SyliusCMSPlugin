<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Condition\StoredCondition;
use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ReferenceProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Targeting\CustomerGroupChoicesProvider;
use Setono\SyliusCMSPlugin\Targeting\CustomerGroupValue;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Sylius\Component\Customer\Model\CustomerGroupInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Service\ResetInterface;

/**
 * A deleted customer group in a block's visibility condition or in the entry's audience: a broken reference, an error.
 * A condition names groups in a parameter that picks from the store's customer groups, such as the customer group
 * kind's, in a shipped kind or a project's own, and publish keeps a deleted one there (Validation\ConditionValidator).
 * Its code is left dangling (ADR 0002) and matches nobody, so "is one of" and "Only these" no longer let anyone in
 * through it, and "is not one of" and "Everyone except these" no longer keep anyone out through it. It's fixed in the
 * builder: at the block, or in the entry's settings. A customer group deleted, or added again with its code, has the
 * entries that may name it counted again. See "Deleted customer groups" in docs/spec/targeting.md and "Problems" in
 * docs/spec/admin.md
 */
final class DeletedCustomerGroup implements ReferenceProblemKindInterface, ResetInterface
{
    public const CODE = 'deleted_customer_group';

    /** @var array<string|null>|null the codes of the store's customer groups */
    private ?array $codes = null;

    /**
     * @param RepositoryInterface<CustomerGroupInterface> $customerGroupRepository
     */
    public function __construct(
        private readonly RepositoryInterface $customerGroupRepository,
        private readonly BlockTypeRegistryInterface $blockTypes,
        private readonly ConditionKindRegistryInterface $conditionKinds,
        private readonly EntryRepositoryInterface $entries,
    ) {
    }

    public static function getReferencedClasses(): array
    {
        return [CustomerGroupInterface::class];
    }

    /**
     * None: a group added again with a code entries name mends their conditions and audiences, so every write counts
     */
    public static function getReferenceFields(): array
    {
        return [];
    }

    public function findEntriesReferencing(array $referenced, array $formerValues = []): iterable
    {
        // The store's groups changed
        $this->reset();

        $codes = [];
        foreach ($referenced as $customerGroup) {
            $code = $customerGroup instanceof CustomerGroupInterface ? $customerGroup->getCode() : null;
            if (null !== $code) {
                $codes[] = $code;
            }
        }

        return [] === $codes ? [] : $this->entries->findByCustomerGroupCodes($codes);
    }

    public function findIn(EntryInterface $entry): iterable
    {
        foreach ($this->deleted($entry->getAudience()->groups) as $group) {
            yield new Problem(
                self::CODE,
                Severity::Error,
                $entry,
                new TranslatableMessage('setono_sylius_cms.problem.deleted_customer_group_in_audience', ['%group%' => $group]),
                Fix::inBuilder($entry),
            );
        }

        yield from $this->inBlocks($entry);
    }

    public function reset(): void
    {
        $this->codes = null;
    }

    /**
     * The deleted groups in the conditions of every block, of any type, at any depth. A condition that isn't in the
     * stored condition format, or of a kind the code no longer has, isn't read
     *
     * @return \Generator<Problem>
     */
    private function inBlocks(EntryInterface $entry): \Generator
    {
        foreach (TreeValues::blocks($entry->getBlockTree()->blocks) as $block) {
            foreach ($block->conditions as $value) {
                $condition = StoredCondition::fromValue($value);
                if (null === $condition) {
                    continue;
                }

                foreach ($this->deleted($this->groups($condition)) as $group) {
                    yield new Problem(
                        self::CODE,
                        Severity::Error,
                        $entry,
                        // "is one of" no longer matches anyone through it, and "is not one of" no longer excludes
                        // anyone
                        new TranslatableMessage($condition->negate ? 'setono_sylius_cms.problem.deleted_customer_group_negated' : 'setono_sylius_cms.problem.deleted_customer_group', [
                            '%group%' => $group,
                            '%block%' => $this->blockTypes->has($block->type) ? new TranslatableMessage($this->blockTypes->get($block->type)->label) : $block->type,
                        ]),
                        Fix::inBuilder($entry, $block->key),
                        $block->key,
                    );
                }
            }
        }
    }

    /**
     * The customer group codes a condition holds in its parameters that pick from the store's customer groups
     *
     * @return array<string>
     */
    private function groups(StoredCondition $condition): array
    {
        $groups = [];
        foreach ($this->conditionKinds->find($condition->kind)->fields ?? [] as $name => $field) {
            $choice = $field->field;
            if (!$choice instanceof Choice || CustomerGroupChoicesProvider::class !== $choice->choicesProvider) {
                continue;
            }

            $value = $condition->params[$name] ?? null;
            $named = $choice->multiple ? (is_array($value) && array_is_list($value) ? $value : []) : [$value];
            $groups = [...$groups, ...array_filter($named, is_string(...))];
        }

        return $groups;
    }

    /**
     * The codes of customer groups the store no longer has, each once. "@guest" and "@no_group" are no groups
     *
     * @param array<string> $groups
     *
     * @return array<string>
     */
    private function deleted(array $groups): array
    {
        $groups = array_unique(array_filter($groups, static fn (string $group): bool => !CustomerGroupValue::isReserved($group)));
        if ([] === $groups) {
            return [];
        }

        $this->codes ??= array_map(static fn (CustomerGroupInterface $customerGroup): ?string => $customerGroup->getCode(), $this->customerGroupRepository->findAll());

        return array_filter($groups, fn (string $group): bool => !in_array($group, $this->codes, true));
    }
}
