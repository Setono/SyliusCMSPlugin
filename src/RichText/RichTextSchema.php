<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\RichText;

use Setono\SyliusCMSPlugin\Binding\StoredBinding;
use Setono\SyliusCMSPlugin\Link\ResolvedLink;
use Tiptap\Core\DOMParser;
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

    /**
     * @var list<Extension> the nodes and the marks but the binding and the link, which each render adds with how its
     *                      bindings and links resolve
     */
    private array $extensions;

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

        $this->extensions = [...$nodes, ...$marks];
        $this->nodes = [...array_map(self::name(...), $nodes), CMSBinding::NAME];
        $this->marks = [...array_map(self::name(...), $marks), CMSLink::NAME];
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
        return [...array_map(array_keys(...), self::attributeRules()), CMSBinding::NAME => ['property', 'params', 'fallback']];
    }

    /**
     * @return array<string, list<string>> mark name => the names of the attributes it keeps
     */
    public function markAttributes(): array
    {
        return [CMSLink::NAME => ['target', 'ref', 'anchor']];
    }

    /**
     * Sanitises a ProseMirror document against the schema, dropping what the schema doesn't have.
     *
     * It walks the document itself, because tiptap-php can't sanitise JSON against a schema: for a document given as
     * an array, Editor::sanitize() only runs Schema::apply(), which strips the marks of nodes that allow none and keeps
     * unknown nodes, marks and attributes
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
     * Reads HTML, or plain text, into a sanitised document with tiptap-php, such as the translation of a translation key
     * in a code preset's rich text field. What the schema doesn't have is dropped, and plain text becomes a paragraph
     *
     * @return array{type: 'doc', content: list<array<string, mixed>>}
     */
    public function fromHtml(string $html): array
    {
        $document = (new DOMParser(new Schema([...$this->extensions, new CMSBinding(), new CMSLink()])))->process($html);

        return $this->sanitize($document) ?? ['type' => 'doc', 'content' => []];
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
            'horizontalRule', CMSBinding::NAME => false,
            default => $this->hasOnlyEmptyContent($document),
        };
    }

    /**
     * Renders a sanitised document to HTML with tiptap-php. A link renders as an <a> to where it resolves, and as plain
     * text when it doesn't
     *
     * @param array{type: 'doc', content: list<array<string, mixed>>} $document
     * @param (\Closure(string, string, ?string): ?ResolvedLink)|null $resolveLink where a link's target, ref and anchor
     *                                                                            lead for the shopper. Without it, no
     *                                                                            link resolves
     * @param bool $marked whether each link and binding token carries its attributes, and a link that doesn't resolve
     *                     renders as a <span>, so typing in place in the builder's preview reads every link and token back
     * @param (\Closure(StoredBinding): ?string)|null $resolveBinding what a binding token's binding resolves to for the
     *                                                             shopper. Without it, every token shows its fallback
     */
    public function render(array $document, ?\Closure $resolveLink = null, bool $marked = false, ?\Closure $resolveBinding = null): string
    {
        return (new DOMSerializer(new Schema([...$this->extensions, new CMSBinding($resolveBinding, $marked), new CMSLink($resolveLink, $marked)])))->process($document);
    }

    /**
     * The links in a sanitised document, in document order, each as often as a text holds it
     *
     * @param array<array-key, mixed> $node
     *
     * @return list<array{target: string, ref: string, anchor: string|null}>
     */
    public function links(array $node): array
    {
        $links = [];
        foreach (is_array($node['marks'] ?? null) ? $node['marks'] : [] as $mark) {
            $link = is_array($mark) && CMSLink::NAME === ($mark['type'] ?? null) ? self::link($mark) : null;
            if (null !== $link) {
                $links[] = $link;
            }
        }

        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            if (is_array($child)) {
                $links = [...$links, ...$this->links($child)];
            }
        }

        return $links;
    }

    /**
     * The binding tokens in a sanitised document, in document order
     *
     * @param array<array-key, mixed> $node
     *
     * @return list<StoredBinding> each token's property, parameters and fallback
     */
    public function bindings(array $node): array
    {
        if (CMSBinding::NAME === ($node['type'] ?? null)) {
            $binding = self::binding($node);

            return null === $binding ? [] : [$binding];
        }

        $bindings = [];
        foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
            if (is_array($child)) {
                $bindings = [...$bindings, ...$this->bindings($child)];
            }
        }

        return $bindings;
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

        if (CMSBinding::NAME === $type) {
            return self::bindingNode($node);
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
     * The attributes each node keeps, with whether a value fits. Other nodes keep none. A link's are kept by link()
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
     * The node's marks the schema has. A mark keeps only the attributes it has, and a link without a target and a ref is
     * dropped, its text kept
     *
     * @param array<array-key, mixed> $node
     *
     * @return list<array{type: string, attrs?: array<string, mixed>}>
     */
    private function marksOf(array $node): array
    {
        $marks = [];
        foreach (is_array($node['marks'] ?? null) ? $node['marks'] : [] as $mark) {
            if (!is_array($mark)) {
                continue;
            }

            $type = $mark['type'] ?? null;
            if (!is_string($type) || !in_array($type, $this->marks, true)) {
                continue;
            }

            if (CMSLink::NAME !== $type) {
                $marks[] = ['type' => $type];

                continue;
            }

            $link = self::link($mark);
            if (null !== $link) {
                $marks[] = ['type' => $type, 'attrs' => $link];
            }
        }

        return $marks;
    }

    /**
     * A binding token as the schema keeps it, {property, params, fallback}, with its marks, or null when it doesn't name
     * a bindable property the way a binding does, and it's dropped. Parameters that aren't a map are none, and an empty
     * fallback is null
     *
     * @param array<array-key, mixed> $node
     *
     * @return array<string, mixed>|null
     */
    private function bindingNode(array $node): ?array
    {
        $binding = self::binding($node);
        if (null === $binding) {
            return null;
        }

        $sanitized = ['type' => CMSBinding::NAME, 'attrs' => [
            'property' => $binding->property,
            // An empty map stays a map in JSON
            'params' => [] === $binding->params ? new \stdClass() : $binding->params,
            'fallback' => $binding->fallback,
        ]];

        $marks = $this->marksOf($node);
        if ([] !== $marks) {
            $sanitized['marks'] = $marks;
        }

        return $sanitized;
    }

    /**
     * A cmsBinding node's binding, or null when its property isn't one. Its fallback is a string or null
     *
     * @param array<array-key, mixed> $node
     */
    private static function binding(array $node): ?StoredBinding
    {
        $attributes = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        $property = $attributes['property'] ?? null;
        if (!is_string($property) || !StoredBinding::isProperty($property)) {
            return null;
        }

        $params = $attributes['params'] ?? null;
        $params = $params instanceof \stdClass ? get_object_vars($params) : $params;
        $fallback = $attributes['fallback'] ?? null;

        /** @var array<string, mixed> $params */
        $params = is_array($params) && ([] === $params || !array_is_list($params)) ? $params : [];

        return new StoredBinding($property, $params, is_string($fallback) && '' !== $fallback ? $fallback : null);
    }

    /**
     * A cmsLink mark's attributes, with an empty anchor as null, or null when it has no target or no ref
     *
     * @param array<array-key, mixed> $mark
     *
     * @return array{target: non-empty-string, ref: non-empty-string, anchor: non-empty-string|null}|null
     */
    private static function link(array $mark): ?array
    {
        $attributes = is_array($mark['attrs'] ?? null) ? $mark['attrs'] : [];
        $target = $attributes['target'] ?? null;
        $ref = $attributes['ref'] ?? null;
        if (!is_string($target) || '' === $target || !is_string($ref) || '' === $ref) {
            return null;
        }

        $anchor = $attributes['anchor'] ?? null;

        return ['target' => $target, 'ref' => $ref, 'anchor' => is_string($anchor) && '' !== $anchor ? $anchor : null];
    }

    private static function name(Extension $extension): string
    {
        /** @var string $name tiptap-php's extensions name themselves in a static property */
        $name = $extension::$name;

        return $name;
    }
}
