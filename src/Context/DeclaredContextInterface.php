<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

use Setono\SyliusCMSPlugin\Model\SnippetInterface;

/**
 * The host-page context a snippet declares, which the slots it's placed in must provide. See "Snippets and their
 * context" in docs/spec/slots.md
 */
interface DeclaredContextInterface
{
    /**
     * The context keys a slot must provide for the snippet to be placed in it: those its published content declares,
     * which it shows with, and those its working copy declares, which it publishes next
     *
     * @return list<string>
     */
    public function toPlace(SnippetInterface $snippet): array;
}
