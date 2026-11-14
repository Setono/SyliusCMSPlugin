<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\RichText;

use Tiptap\Core\DOMSerializer;
use Tiptap\Core\Extension;
use Tiptap\Core\Schema;
use Tiptap\Marks;
use Tiptap\Nodes;

/**
 * The one rich text schema, shared by the builder's editor and the shop, built from ueberdosis/tiptap-php's extensions.
 * See "Rich text" in docs/spec/content-model.md
 */
final readonly class RichTextSchema
{
    /** The heading levels rich text has. A page's own headings are Heading blocks */
    public const HEADING_LEVELS = [2, 3, 4];

    private Schema $schema;

    /** @var list<string> */
    private array $nodes;

    /** @var list<string> */
    private array $marks;

    public function __construct()
    {
        $nodes = [
            new Nodes\Document(),
            new Nodes\Paragraph(),
            new Nodes\Text(),
            // Sanitising turns a heading of any other level than HEADING_LEVELS into a paragraph
            new Nodes\Heading(),
            new Nodes\BulletList(),
            new Nodes\OrderedList(),
            new Nodes\ListItem(),
            new Nodes\Blockquote(),
            new Nodes\HardBreak(),
            new Nodes\HorizontalRule(),
        ];
        $marks = [
            new Marks\Bold(),
            new Marks\Italic(),
            new Marks\Underline(),
            new Marks\Strike(),
            new Marks\Code(),
        ];

        $this->schema = new Schema([...$nodes, ...$marks]);
        $this->nodes = array_map(self::name(...), $nodes);
        $this->marks = array_map(self::name(...), $marks);
    }

    /**
     * @return list<string>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /**
     * @return list<string>
     */
    public function marks(): array
    {
        return $this->marks;
    }

    /**
     * @return array<string, list<string>> node name => the names of the attributes it keeps
     */
    public function attributes(): array
    {
        return array_map(array_keys(...), self::attributeRules());
    }

    /**
     * Sanitises a ProseMirror document against the schema, dropping what the schema doesn't have
     *
     * @return array{type: 'doc', content: list<array<string, mixed>>}|null null when the value isn't a document at all
     */
    public function sanitize(mixed $value): ?array
    {
        if (!is_array($value) || 'doc' !== ($value['type'] ?? null)) {
            return null;
        }

        return ['type' => 'doc', 'content' => $this->content($value)];
    }

    /**
     * Whether a sanitised document is empty, which counts as no value: it has nothing but empty blocks, whitespace and
     * line breaks. It's what Tiptap's isNodeEmpty() answers when it ignores whitespace
     *
     * @param array<array-key, mixed> $document
     */
    public function isEmpty(array $document): bool
    {
        // A line break holds nothing, so it counts as empty like an empty paragraph does
        return match ($document['type'] ?? null) {
            'text' => 1 !== preg_match('/(*UCP)\S/u', is_string($document['text'] ?? null) ? $document['text'] : ''),
            'horizontalRule' => false,
            default => $this->hasOnlyEmptyContent($document),
        };
    }

    /**
     * Renders a sanitised document to HTML with tiptap-php
     *
     * @param array{type: 'doc', content: list<array<string, mixed>>} $document
     */
    public function render(array $document): string
    {
        return (new DOMSerializer($this->schema))->process($document);
    }

    /**
     * @param array<array-key, mixed> $node
     */
    private function hasOnlyEmptyContent(array $node): bool
    {
        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            if (is_array($child) && !$this->isEmpty($child)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<array-key, mixed> $node
     *
     * @return list<array<string, mixed>>
     */
    private function content(array $node): array
    {
        $content = [];
        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            $child = $this->node($child);
            if (null !== $child) {
                $content[] = $child;
            }
        }

        return $content;
    }

    /**
     * @return array<string, mixed>|null null when the node is dropped
     */
    private function node(mixed $node): ?array
    {
        if (!is_array($node)) {
            return null;
        }

        $type = $node['type'] ?? null;
        if (!is_string($type) || 'doc' === $type || !in_array($type, $this->nodes, true)) {
            return null;
        }

        $given = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        $attributes = [];
        foreach (self::attributeRules()[$type] ?? [] as $name => $fits) {
            $value = $given[$name] ?? null;
            if ($fits($value)) {
                $attributes[$name] = $value;
            }
        }

        if ('heading' === $type && !isset($attributes['level'])) {
            // A heading of a level rich text doesn't have is dropped, and its text kept in a paragraph
            $type = 'paragraph';
        }

        $sanitized = ['type' => $type];
        if ([] !== $attributes) {
            $sanitized['attrs'] = $attributes;
        }

        if ('text' === $type) {
            $text = $node['text'] ?? null;
            if (!is_string($text) || '' === $text) {
                return null;
            }

            $sanitized['text'] = $text;
        }

        if (isset($node['content'])) {
            $sanitized['content'] = $this->content($node);
        }

        $marks = $this->marksOf($node);
        if ([] !== $marks) {
            $sanitized['marks'] = $marks;
        }

        return $sanitized;
    }

    /**
     * The attributes each node keeps, with whether a value fits. Other nodes keep none, and no mark has any yet
     *
     * @return array<string, array<string, \Closure(mixed): bool>>
     */
    private static function attributeRules(): array
    {
        return [
            'heading' => ['level' => static fn (mixed $level): bool => in_array($level, self::HEADING_LEVELS, true)],
            'orderedList' => ['start' => is_int(...)],
        ];
    }

    /**
     * @param array<array-key, mixed> $node
     *
     * @return list<array{type: string}>
     */
    private function marksOf(array $node): array
    {
        $marks = [];
        foreach (is_array($node['marks'] ?? null) ? $node['marks'] : [] as $mark) {
            $type = is_array($mark) ? ($mark['type'] ?? null) : null;
            if (is_string($type) && in_array($type, $this->marks, true)) {
                $marks[] = ['type' => $type];
            }
        }

        return $marks;
    }

    private static function name(Extension $extension): string
    {
        /** @var string $name tiptap-php's extensions name themselves in a static property */
        $name = $extension::$name;

        return $name;
    }
}
