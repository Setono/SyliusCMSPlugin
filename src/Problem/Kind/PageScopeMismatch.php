<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Condition\StoredCondition;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ContextKeyRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Registry\SlotRegistryInterface;
use Setono\SyliusCMSPlugin\Repository\PlacementRepositoryInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A page-scope condition of a snippet's placement that the code broke, a warning for each: one of a kind the code no
 * longer has, or one whose kind needs a context key the slot no longer provides. The condition is kept and the
 * placement shows nothing. It's fixed by editing the page scope in the Placements section of the snippet's overview
 * page. A project may remove a condition kind on purpose, so setono:cms:lint doesn't report it. A placement in a slot
 * the code no longer declares is flagged as such. See "Mismatches" in docs/spec/slots.md and "Problems" in
 * docs/spec/admin.md
 */
final readonly class PageScopeMismatch implements ProblemKindInterface
{
    public const CODE = 'page_scope_mismatch';

    public function __construct(
        private PlacementRepositoryInterface $placements,
        private SlotRegistryInterface $slots,
        private ConditionKindRegistryInterface $conditionKinds,
        private ContextKeyRegistryInterface $contextKeys,
    ) {
    }

    public function findIn(EntryInterface $entry): iterable
    {
        if (!$entry instanceof SnippetInterface) {
            return;
        }

        foreach ($this->placements->findBySnippet($entry) as $placement) {
            $slot = $this->slots->find($placement->getSlot());
            if (null === $slot) {
                continue;
            }

            foreach ($placement->getPageScope() as $value) {
                $condition = StoredCondition::fromValue($value);
                if (null !== $condition) {
                    yield from $this->mismatches($entry, $slot, $condition->kind);
                }
            }
        }
    }

    /**
     * @return iterable<Problem>
     */
    private function mismatches(SnippetInterface $snippet, SlotMetadata $slot, string $code): iterable
    {
        $slotLabel = new TranslatableMessage($slot->label);
        if (!$this->conditionKinds->has($code)) {
            yield $this->problem($snippet, new TranslatableMessage('setono_sylius_cms.problem.page_scope_removed_kind', ['%slot%' => $slotLabel, '%kind%' => $code]));

            return;
        }

        $kind = $this->conditionKinds->get($code);
        foreach ($kind->needs as $key) {
            if (!$slot->providesAll([$key])) {
                yield $this->problem($snippet, new TranslatableMessage('setono_sylius_cms.problem.page_scope_unprovided', [
                    '%slot%' => $slotLabel,
                    '%kind%' => new TranslatableMessage($kind->label),
                    '%context%' => $this->contextKeys->has($key) ? new TranslatableMessage($this->contextKeys->get($key)->label) : $key,
                ]));
            }
        }
    }

    private function problem(SnippetInterface $snippet, TranslatableMessage $message): Problem
    {
        return new Problem(self::CODE, Severity::Warning, $snippet, $message, Fix::inPlacements($snippet));
    }
}
