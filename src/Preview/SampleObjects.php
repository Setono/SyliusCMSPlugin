<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Preview;

use Setono\SyliusCMSPlugin\Condition\ConditionMatcher;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Field\ReferenceType;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\SlotMetadata;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoaderInterface;
use Setono\SyliusCMSPlugin\Rendering\ReferenceMap;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;

/**
 * The sample objects a snippet's preview shows it with: products, taxons and entries, which the builder searches and
 * the shop loads by reference, as the shopper sees them, so a sample object has a page. The preview starts with one that
 * matches the placement's page scope, and warns when the editor picks one outside it. See "Snippet preview" in
 * docs/spec/builder.md
 *
 * @internal
 */
final readonly class SampleObjects
{
    /** The context keys whose objects can be sample objects. A project's own context key has none */
    public const KEYS = [ReferenceType::PRODUCT, ReferenceType::TAXON, ReferenceType::ENTRY];

    public function __construct(
        private SampleCandidatesInterface $candidates,
        private ReferenceLoaderInterface $referenceLoader,
        private ConditionMatcher $conditions,
        private HostPageUrlGeneratorInterface $hostPageUrls,
    ) {
    }

    /**
     * The first of the context keys whose objects can be sample objects, such as product for a slot on product pages,
     * or null when none can
     *
     * @param list<string> $keys
     */
    public static function keyOf(array $keys): ?string
    {
        foreach ($keys as $key) {
            if (in_array($key, self::KEYS, true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * How a sample object of the key is referred to, or null when the object isn't one of the key's
     */
    public static function of(string $key, object $object): ?SampleObject
    {
        $ref = match (true) {
            ReferenceType::PRODUCT === $key && $object instanceof ProductInterface,
            ReferenceType::TAXON === $key && $object instanceof TaxonInterface => $object->getCode(),
            ReferenceType::ENTRY === $key && $object instanceof EntryInterface => $object->getUuid(),
            default => null,
        };

        return null === $ref ? null : new SampleObject($key, $ref);
    }

    /**
     * The sample object as the shopper sees it, or null when they can't, such as a disabled or deleted product
     */
    public function load(SampleObject $sample, RenderContext $context): ?object
    {
        return $this->referenceLoader->load([new Reference($sample->key, $sample->ref, false)], $context)->get($sample->key, $sample->ref);
    }

    /**
     * The sample object a preview of a placement in the slot, with the page scope, starts with: the first candidate
     * whose page shows the slot and that matches the scope, or else the first whose page shows the slot, which the
     * preview then warns is outside the scope. A candidate whose page doesn't show the slot, such as an entry whose
     * layout doesn't render it, comes last, and the preview shows it on a plain page. Null when the key has no candidate
     * the shopper can see
     *
     * @param list<array<string, mixed>> $pageScope
     * @param SlotMetadata|null $slot the placement's slot, or null for a snippet previewed without a placement, which
     *                                any candidate's page shows
     */
    public function starting(string $key, array $pageScope, RenderContext $context, ?SlotMetadata $slot = null): ?object
    {
        $refs = [];
        foreach ($this->candidates->refs($key, $pageScope, $context) as $ref) {
            $refs[$ref] = new Reference($key, $ref, false);
        }

        if ([] === $refs) {
            return null;
        }

        $references = $this->referenceLoader->load([...array_values($refs), ...$this->conditions->references($pageScope)], $context);

        /** @var array<int, object> $best by rank, lower first: 1 outside the scope, 2 without the slot on its page, 3 both */
        $best = [];
        foreach (array_keys($refs) as $ref) {
            $candidate = $references->get($key, (string) $ref);
            if (null === $candidate) {
                continue;
            }

            $rank = ($this->showsTheSlot($slot, $candidate, $context) ? 0 : 2) + ($this->matchesWith($pageScope, $key, $candidate, $context, $references) ? 0 : 1);
            if (0 === $rank) {
                return $candidate;
            }

            $best[$rank] ??= $candidate;
        }

        ksort($best);

        return [] === $best ? null : $best[array_key_first($best)];
    }

    /**
     * Whether the sample object's page is inside the page scope
     *
     * @param list<array<string, mixed>> $pageScope
     */
    public function matches(array $pageScope, string $key, object $sample, RenderContext $context): bool
    {
        return $this->matchesEach($pageScope, $key, [$sample], $context)[0];
    }

    /**
     * Whether each sample object's page is inside the page scope, whose references load once for all of them
     *
     * @template T of array-key
     *
     * @param list<array<string, mixed>> $pageScope
     * @param array<T, object> $samples
     *
     * @return array<T, bool>
     */
    public function matchesEach(array $pageScope, string $key, array $samples, RenderContext $context): array
    {
        if ([] === $pageScope) {
            return array_map(static fn (): bool => true, $samples);
        }

        $references = $this->referenceLoader->load($this->conditions->references($pageScope), $context);

        return array_map(fn (object $sample): bool => $this->matchesWith($pageScope, $key, $sample, $context, $references), $samples);
    }

    /**
     * Whether the sample object has a page that shows the slot, or any page without a slot
     */
    private function showsTheSlot(?SlotMetadata $slot, object $sample, RenderContext $context): bool
    {
        return null === $slot || null !== $this->hostPageUrls->generate($slot, $sample, $context->channel, $context->localeCode);
    }

    /**
     * @param list<array<string, mixed>> $pageScope
     */
    private function matchesWith(array $pageScope, string $key, object $sample, RenderContext $context, ReferenceMap $references): bool
    {
        return $this->conditions->matchesAll($pageScope, $context->withHostPageContext([$key => $sample]), $references);
    }
}
