<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\SnippetInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\ContextKeyRegistryInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A snippet that declares a context key the code no longer declares, a warning for each key. The snippet is kept, but
 * no slot provides the key, so none of its placements shows it. It's fixed by picking another context in the builder's
 * entry settings. A project may remove a context key on purpose, so setono:cms:lint doesn't report it. See "Mismatches"
 * in docs/spec/slots.md and "Problems" in docs/spec/admin.md
 */
final readonly class RemovedContextKey implements ProblemKindInterface
{
    public const CODE = 'removed_context_key';

    public function __construct(private ContextKeyRegistryInterface $contextKeys)
    {
    }

    public function findIn(EntryInterface $entry): iterable
    {
        if (!$entry instanceof SnippetInterface) {
            return;
        }

        foreach ($entry->getContext() as $key) {
            if ($this->contextKeys->has($key)) {
                continue;
            }

            yield new Problem(
                self::CODE,
                Severity::Warning,
                $entry,
                new TranslatableMessage('setono_sylius_cms.problem.removed_context_key', ['%key%' => $key]),
                Fix::inBuilder($entry),
            );
        }
    }
}
