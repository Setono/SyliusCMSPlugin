<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Psr\Container\ContainerInterface;
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
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A reference in a field of a block or of the layout to a record that was deleted, such as a media item: a broken
 * reference, an error. Rendering skips it, so the field is empty or the list leaves it out (ADR 0002). It's fixed in the
 * builder, at the block and in the locale of the value it's in. Each reference type with a loader is checked with one
 * query per entry. A media item written, such as one deleted, has the entries that may refer to it counted again.
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
        return [MediaItemInterface::class];
    }

    public function findEntriesReferencing(array $referenced): iterable
    {
        $ids = [];
        foreach ($referenced as $record) {
            if ($record instanceof MediaItemInterface) {
                $ids[] = $record->getUuid();
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

        if (null === $reference->block) {
            $layout = $this->layouts->get($contentType, (string) $entry->getBlockTree()->layout?->code);
            $message = new TranslatableMessage('setono_sylius_cms.problem.broken_layout_reference', [
                '%field%' => new TranslatableMessage($layout->fields[$reference->field]->field->label),
                '%type%' => $type,
            ]);
        } else {
            $blockType = $this->blockTypes->get((string) $entry->getBlockTree()->findBlock($reference->block)?->type);
            $message = new TranslatableMessage('setono_sylius_cms.problem.broken_reference', [
                '%field%' => new TranslatableMessage($blockType->fields[$reference->field]->field->label),
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
