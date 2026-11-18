<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Unit\Preview\Fixture;

use Setono\SyliusCMSPlugin\Attribute\AsConditionKind;
use Setono\SyliusCMSPlugin\Condition\ConditionKindInterface;
use Setono\SyliusCMSPlugin\Condition\Reads;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

/**
 * A page-scope condition kind that matches the page of the entry with a UUID
 */
#[AsConditionKind(code: 'entry_uuid_is', label: 'Entry UUID is', needs: ['entry'], reads: [Reads::HostPage], params: EntryUuidIsParams::class)]
final class EntryUuidIs implements ConditionKindInterface
{
    public function matches(object $params, RenderContext $context): bool
    {
        $entry = $context->get('entry');

        return $params instanceof EntryUuidIsParams && $entry instanceof EntryInterface && $entry->getUuid() === $params->uuid;
    }
}
