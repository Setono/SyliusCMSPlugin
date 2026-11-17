<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Twig;

/**
 * Builds the opening tags the cms_* functions print
 *
 * @internal
 */
final class HtmlTag
{
    private function __construct()
    {
    }

    /**
     * An opening tag with the attributes that have a value, escaped, in the order given. True prints a boolean attribute,
     * and null or false leaves the attribute out
     *
     * @param array<string, string|int|bool|null> $attributes
     */
    public static function open(string $name, array $attributes): string
    {
        $html = '<' . $name;
        foreach ($attributes as $attribute => $value) {
            if (true === $value) {
                $html .= ' ' . $attribute;
            } elseif (null !== $value && false !== $value) {
                $html .= sprintf(' %s="%s"', $attribute, htmlspecialchars((string) $value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'));
            }
        }

        return $html . '>';
    }
}
