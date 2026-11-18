<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

use Setono\SyliusCMSPlugin\Model\EntryInterface;

/**
 * Finds one kind of thing that depends on the host-page context an entry provides or a snippet declares, such as the
 * blocks whose types need context keys, or a snippet's placements. Each finder is a service tagged
 * setono_sylius_cms.context_dependency_finder, and ContextCheck asks them all, for publish and for a change of a
 * snippet's declared context, so a kind of dependency joins both by adding a finder: sources in list fields, bindings
 * and Snippet blocks each add theirs. See "Snippets and their context" in docs/spec/slots.md
 *
 * @internal
 */
interface ContextDependencyFinderInterface
{
    /**
     * @param EntryInterface $workingCopy the entry as it would be published, or as it's being saved
     *
     * @return iterable<ContextDependency>
     */
    public function findIn(EntryInterface $workingCopy): iterable;
}
