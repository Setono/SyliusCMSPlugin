<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Rendering;

/**
 * Whether a slot shows one of its placements, and if not, the first reason why, in the order a slot checks them. See
 * "Rendering a slot" in docs/spec/rendering.md
 */
enum PlacementOutcome: string
{
    /** It shows: its scope matches, its snippet is visible, and the slot's cap wasn't reached before it */
    case Wins = 'wins';

    /** Its page scope doesn't match the page */
    case ScopeDoesNotMatch = 'scope_does_not_match';

    /**
     * Its snippet isn't visible to the shopper: not published, disabled, outside its date window, not in the channel,
     * not translated or held back, or outside its audience
     */
    case SnippetNotVisible = 'snippet_not_visible';

    /** Its snippet's published content needs a context key the slot doesn't provide, such as one the code removed */
    case ContextNotProvided = 'context_not_provided';

    /** It matches, but the capped slot's first `cap` matching placements came before it */
    case CapReached = 'cap_reached';
}
