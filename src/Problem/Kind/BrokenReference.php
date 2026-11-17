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
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\LayoutRegistryInterface;
use Setono\SyliusCMSPlugin\Rendering\ReferenceTypeLoaderInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Tree\FoundReference;
use Setono\SyliusCMSPlugin\Tree\ReferenceFinder;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A reference in a field of a block or of the layout to a record that was deleted, such as a media item, or the
 * product a link leads to: a broken reference, an error. Rendering skips it, so the field is empty, the list leaves it
 * out, the link isn't rendered or a link in rich text shows as plain text (ADR 0002). It's fixed in the builder, at the
 * block and in the locale of the value it's in. Each reference type with a loader is checked with one query per entry. A
 * media item, entry, product or taxon written, such as one deleted, has the entries that may refer to it counted again.
 * See "References" in docs/spec/content-model.md and "Problems" in docs/spec/admin.md
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
    ) {
    }

    public static function getReferencedClasses(): array
    {
        return [MediaItemInterface::class, EntryInterface::class, ProductInterface::class, TaxonInterface::class];
    }

    public function findEntriesReferencing(array $referenced): iterable
    {
        $ids = [];
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

        return [] === $ids ? [] : $this->entries->findReferencing($ids);
    }

    public function findIn(EntryInterface $entry): iterable
    {
        $contentType = $this->contentTypes->getByEntry($entry)->code;

        /** @var list<FoundReference> $found */
        $found = [];
        $ids = [];
        foreach ($this->referenceFinder->inTree($entry->getBlockTree(), $contentType) as $reference) {
            if ($this->typeLoaders->has($reference->reference->type)) {
                $found[] = $reference;
                $ids[$reference->reference->type][$reference->reference->id] = true;
            }
        }

        $existing = [];
        foreach ($ids as $type => $typeIds) {
            $loader = $this->typeLoaders->get($type);
            if ($loader instanceof ReferenceTypeLoaderInterface) {
                $existing[$type] = array_fill_keys($loader->existing(array_map(strval(...), array_keys($typeIds))), true);
            }
        }

        foreach ($found as $reference) {
            if (isset($existing[$reference->reference->type][$reference->reference->id])) {
                continue;
            }

            yield $this->problem($entry, $contentType, $reference);
        }
    }

    private function problem(EntryInterface $entry, string $contentType, FoundReference $reference): Problem
    {
        $type = new TranslatableMessage(sprintf('setono_sylius_cms.reference_type.%s', $reference->reference->type));

        // A link in rich text that doesn't resolve still shows its text, as plain text
        if (null === $reference->block) {
            $layout = $this->layouts->get($contentType, (string) $entry->getBlockTree()->layout?->code);
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
