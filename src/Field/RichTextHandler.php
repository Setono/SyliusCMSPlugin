<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Link\LinkResolver;
use Setono\SyliusCMSPlugin\Link\ResolvedLink;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\RichText\RichTextSchema;
use Setono\SyliusCMSPlugin\Value\RichText as RichTextValue;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Handles rich text: a ProseMirror document, sanitised against the one rich text schema and rendered to HTML when it's
 * hydrated. Its links resolve through their link targets, from the records loaded before rendering, and one that doesn't
 * resolve renders as plain text. See "Rich text" in docs/spec/content-model.md
 */
final readonly class RichTextHandler implements SanitizingFieldHandlerInterface, EmptinessCheckingFieldHandlerInterface, FitCheckingFieldHandlerInterface
{
    public function __construct(
        private RichTextSchema $schema,
        private LinkResolver $linkResolver,
    ) {
    }

    public function schema(Field $field): array
    {
        return [
            'type' => 'object',
            'properties' => ['type' => ['const' => 'doc'], 'content' => ['type' => 'array']],
            'required' => ['type', 'content'],
        ];
    }

    /**
     * An empty document counts as missing, so a translatable value falls back past it
     */
    public function hydrate(Field $field, mixed $value, HydrationContext $context): ?RichTextValue
    {
        $document = $this->schema->sanitize($value);
        if (null === $document || $this->schema->isEmpty($document)) {
            return null;
        }

        /** @var array<string, ResolvedLink|null> $resolved each link's target, ref and anchor => where it leads */
        $resolved = [];
        $resolveLink = function (string $target, string $ref, ?string $anchor) use (&$resolved, $context): ?ResolvedLink {
            $key = serialize([$target, $ref, $anchor]);
            if (!array_key_exists($key, $resolved)) {
                $resolved[$key] = $this->linkResolver->resolve($target, $ref, $anchor, $context);
            }

            return $resolved[$key];
        };
        $inBuilder = RenderContext::PREVIEW_MODE_BUILDER === $context->renderContext->previewMode;
        $html = $this->schema->render($document, $resolveLink, $inBuilder);

        // Rendering resolved every link
        return new RichTextValue($document, $html, count(array_filter($resolved, is_null(...))));
    }

    /**
     * The records its links lead to, each once, such as a product. A link to a URL refers to none
     */
    public function references(Field $field, mixed $value): iterable
    {
        $document = $this->schema->sanitize($value);
        if (null === $document) {
            return [];
        }

        $references = [];
        foreach ($this->schema->links($document) as $link) {
            $reference = $this->linkResolver->reference($link['target'], $link['ref']);
            if (null !== $reference) {
                $references[$reference->type . "\0" . $reference->id] = $reference;
            }
        }

        return array_values($references);
    }

    /**
     * A document fits, even an empty one, which counts as missing anyway
     */
    public function fits(Field $field, mixed $value): bool
    {
        return null !== $this->schema->sanitize($value);
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
        if (null === $value || $this->fits($field, $value)) {
            return;
        }

        $context->buildViolation('setono_sylius_cms.field.not_rich_text')->addViolation();
    }

    /**
     * A document with nothing in it, such as one empty paragraph, is empty. A value that isn't a document isn't:
     * validate() refuses it
     */
    public function isEmpty(Field $field, mixed $value): bool
    {
        $document = $this->schema->sanitize($value);

        return null !== $document && $this->schema->isEmpty($document);
    }

    /**
     * Drops what the rich text schema doesn't have. A value that isn't a document at all is saved as it is,
     * because invalid content saves: it counts as missing, and publish validates
     */
    public function sanitize(Field $field, mixed $value): mixed
    {
        return $this->schema->sanitize($value) ?? $value;
    }
}
