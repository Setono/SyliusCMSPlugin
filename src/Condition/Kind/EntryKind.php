<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Condition\Kind;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * Matches when the entry being shown, such as the page whose layout renders the slot, is one of the entries. A deleted
 * entry, or one the shopper may not see, matches nothing. See "Condition kinds" in docs/spec/targeting.md
 */
#[AsConditionKind(code: self::CODE, label: 'setono_sylius_cms.condition_kind.entry', needs: ['entry'], reads: [Reads::HostPage], params: EntryParams::class)]
final class EntryKind implements ConditionKindInterface
{
    public const CODE = 'entry';

    public function matches(object $params, RenderContext $context): bool
    {
        if (!$params instanceof EntryParams) {
            throw new \InvalidArgumentException(sprintf('The entry condition takes %s, got %s', EntryParams::class, $params::class));
        }

        $entry = $context->get('entry');
        if (!$entry instanceof EntryInterface || null === $params->entries) {
            return false;
        }

        foreach ($params->entries as $picked) {
            if ($picked->getUuid() === $entry->getUuid()) {
                return true;
            }
        }

        return false;
    }
}
