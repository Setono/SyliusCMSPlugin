<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Tests\Functional\Fixture;

use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Text;

/**
 * A project's block type at version 2: its field "text" was renamed to "message", and CalloutFromOne upgrades the
 * blocks saved with version 1. Only UpgradesKernel registers it
 */
#[BlockType(code: 'callout', label: 'Callout', template: 'cms/block/callout.html.twig', version: 2)]
final class Callout
{
    #[Text(label: 'Message')]
    public ?string $message = null;
}
