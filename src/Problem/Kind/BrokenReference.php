<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Psr\Container\ContainerInterface;
use Setono\SyliusCMSPlugin\Field\RichText;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ReferenceProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeFields;
use Setono\SyliusCMSPlugin\Registry\ContentTypeMetadata;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldReader;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceTypeLoaderInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tree\FoundReference;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A reference in a field of a block, of the layout or of the entry itself to a record that was deleted, such as a media
 * item, or the product a link leads to: a broken reference, an error. Rendering skips it, so the field is empty, the
 * list leaves it out, the link isn't rendered or a link in rich text shows as plain text (ADR 0002). It's fixed in the
 * builder, at the block and in the locale of the value it's in. Each reference type with a loader is checked with one query per entry. A
 * media item, entry, product or taxon deleted, or one whose UUID or code changes, has the entries that may refer to it
 * counted again; other writes of them run no query. See "References" in docs/spec/content-model.md and "Problems" in
 * docs/spec/admin.md
 */
final readonly class BrokenReference implements ReferenceProblemKindInterface
{
    public const CODE = 'broken_reference';

    /**
     * @param ContainerInterface $typeLoaders the ReferenceTypeLoaderInterface of each reference type, by type
     */
    public function __construct(
        private ReferenceFinder $referenceFinder,
        private ContentTypeRegistryInterface $contentTypes,
        private BlockTypeRegistryInterface $blockTypes,
        private LayoutRegistryInterface $layouts,
        private ContainerInterface $typeLoaders,
        private EntryRepositoryInterface $entries,
        private ContentTypeFields $contentTypeFields = new ContentTypeFields(new FieldReader()),
    ) {
    }

    public static function getReferencedClasses(): array
    {
        return [MediaItemInterface::class, EntryInterface::class, ProductInterface::class, TaxonInterface::class];
    }

    /**
     * Fields refer to them by their UUIDs and codes (ADR 0002), so only deleting one or changing those breaks or mends a
     * reference: its existence is all this kind reads, and not whether it's enabled or published
     */
    public static function getReferenceFields(): array
    {
        return [
            MediaItemInterface::class => 'uuid',
            EntryInterface::class => 'uuid',
            ProductInterface::class => 'code',
            TaxonInterface::class => 'code',
        ];
    }

    public function findEntriesReferencing(array $referenced, array $formerValues = []): iterable
    {
        // The entries that referred to a former code may still, and now hold a broken reference
        $ids = $formerValues;
        foreach ($referenced as $record) {
            $id = match (true) {
                $record instanceof MediaItemInterface, $record instanceof EntryInterface => $record->getUuid(),
                $record instanceof ProductInterface, $record instanceof TaxonInterface => $record->getCode(),
                default => null,
            };

            if (null !== $id) {
                $ids[] = $id;
            }
        }

        return [] === $ids ? [] : $this->entries->findReferencing(array_values(array_unique($ids)));
    }

    public function findIn(EntryInterface $entry): iterable
    {
        $contentType = $this->contentTypes->getByEntry($entry);

        /** @var list<FoundReference> $found */
        $found = array_values(array_filter(
            iterator_to_array($this->referenceFinder->inEntry($entry, $contentType), false),
            fn (FoundReference $reference): bool => $this->typeLoaders->has($reference->reference->type),
        ));

        $existing = [];
        foreach (ReferenceLoader::idsByType(ReferenceFinder::references($found)) as $type => $ids) {
            $loader = $this->typeLoaders->get($type);
            if ($loader instanceof ReferenceTypeLoaderInterface) {
                $existing[$type] = array_fill_keys($loader->existing($ids), true);
            }
        }

        foreach ($found as $reference) {
            if (isset($existing[$reference->reference->type][$reference->reference->id])) {
                continue;
            }

            yield $this->problem($entry, $contentType, $reference);
        }
    }

    private function problem(EntryInterface $entry, ContentTypeMetadata $contentType, FoundReference $reference): Problem
    {
        $type = new TranslatableMessage(sprintf('setono_sylius_cms.reference_type.%s', $reference->reference->type));

        // A link in rich text that doesn't resolve still shows its text, as plain text
        if ($reference->inEntry) {
            $field = ($this->contentTypeFields->shared($contentType) + $this->contentTypeFields->translatable($contentType))[$reference->field]->field;
            $message = new TranslatableMessage($field instanceof RichText ? 'setono_sylius_cms.problem.broken_entry_rich_text_reference' : 'setono_sylius_cms.problem.broken_entry_reference', [
                '%field%' => new TranslatableMessage($field->label),
                '%type%' => $type,
            ]);
        } elseif (null === $reference->block) {
            $layout = $this->layouts->get($contentType->code, (string) $entry->getBlockTree()->layout?->code);
            $field = $layout->fields[$reference->field]->field;
            $message = new TranslatableMessage($field instanceof RichText ? 'setono_sylius_cms.problem.broken_layout_rich_text_reference' : 'setono_sylius_cms.problem.broken_layout_reference', [
                '%field%' => new TranslatableMessage($field->label),
                '%type%' => $type,
            ]);
        } else {
            $blockType = $this->blockTypes->get((string) $entry->getBlockTree()->findBlock($reference->block)?->type);
            $field = $blockType->fields[$reference->field]->field;
            $message = new TranslatableMessage($field instanceof RichText ? 'setono_sylius_cms.problem.broken_rich_text_reference' : 'setono_sylius_cms.problem.broken_reference', [
                '%field%' => new TranslatableMessage($field->label),
                '%block%' => new TranslatableMessage($blockType->label),
                '%type%' => $type,
            ]);
        }

        return new Problem(
            self::CODE,
            Severity::Error,
            $entry,
            $message,
            Fix::inBuilder($entry, $reference->block, $reference->locale),
            $reference->block,
            $reference->field,
            $reference->locale,
        );
    }
}
