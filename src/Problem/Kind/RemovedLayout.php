<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * An entry whose layout was removed from code or disabled, a warning. It keeps the layout's code in its tree and
 * renders in its content type's default layout. It's fixed by picking a layout in the builder's entry settings.
 * See "A removed layout" in docs/spec/layouts-and-presets.md and "Problems" in docs/spec/admin.md
 */
final readonly class RemovedLayout implements ProblemKindInterface
{
    public const CODE = 'removed_layout';

    public function __construct(
        private LayoutRegistryInterface $layouts,
        private ContentTypeRegistryInterface $contentTypes,
    ) {
    }

    public function findIn(EntryInterface $entry): iterable
    {
        $stored = $entry->getBlockTree()->layout;
        if (null === $stored) {
            return;
        }

        $layout = $this->layouts->resolve($this->contentTypes->getByEntry($entry)->code, $stored->code);
        if (null === $layout || $layout->code === $stored->code) {
            return;
        }

        yield new Problem(
            self::CODE,
            Severity::Warning,
            $entry,
            new TranslatableMessage('setono_sylius_cms.problem.removed_layout', ['%layout%' => $stored->code, '%default%' => new TranslatableMessage($layout->label)]),
            Fix::inBuilder($entry),
        );
    }
}
