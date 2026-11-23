<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Psr\Container\ContainerInterface;
use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Model\MediaItemInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ReferenceProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Rendering\ReferenceLoader;
use Setono\SyliusCMSPlugin\Rendering\ReferenceTypeLoaderInterface;
use Setono\SyliusCMSPlugin\Repository\EntryRepositoryInterface;
use Setono\SyliusCMSPlugin\Targeting\ChannelChoicesProvider;
use Setono\SyliusCMSPlugin\Targeting\LocaleChoicesProvider;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Service\ResetInterface;

/**
 * A block's visibility condition that names a channel or a locale the store no longer has, or whose parameter refers to
 * a record that was deleted, such as the products of a product condition: a broken reference, an error. The code is
 * left dangling (ADR 0002), and the condition no longer counts it, so "is one of" no longer matches through it and "is
 * not one of" no longer excludes anything through it. It's fixed in the builder, at the block. A parameter names a
 * channel or a locale when it's a Choice of the store's channels or locales; each reference type with a loader is
 * checked with one query per entry, as BrokenReference checks fields. A deleted customer group is DeletedCustomerGroup's
 * problem. A condition of a kind the code no longer has, and a block of a type it no longer has with what's inside it,
 * aren't read. A channel, locale or any record BrokenReference reads deleted, or given another code or UUID, has the
 * entries that may name it counted again. See "Deleted customer groups" in docs/spec/targeting.md and "Problems" in
 * docs/spec/admin.md
 */
final class BrokenConditionReference implements ReferenceProblemKindInterface, ResetInterface
{
    public const CODE = 'broken_condition_reference';

    /** The choices providers whose choices are the store's records, with the type a problem names them by */
    private const STORE_CHOICES = [ChannelChoicesProvider::class => 'channel', LocaleChoicesProvider::class => 'locale'];

    /** @var array<string, list<string>> the codes of the store's channels and locales, by type, as they were read */
    private array $codes = [];

    /**
     * @param ContainerInterface $typeLoaders the ReferenceTypeLoaderInterface of each reference type, by type
     * @param RepositoryInterface<ChannelInterface> $channelRepository
     * @param RepositoryInterface<LocaleInterface> $localeRepository
     */
    public function __construct(
        private readonly BlockTypeRegistryInterface $blockTypes,
        private readonly ConditionKindRegistryInterface $conditionKinds,
        private readonly FieldHandlerLocator $fieldHandlers,
        private readonly ContainerInterface $typeLoaders,
        private readonly RepositoryInterface $channelRepository,
        private readonly RepositoryInterface $localeRepository,
        private readonly EntryRepositoryInterface $entries,
    ) {
    }

    /**
     * The store's channels and locales, and the records BrokenReference reads, which condition parameters refer to as
     * fields do
     */
    public static function getReferencedClasses(): array
    {
        return [ChannelInterface::class, LocaleInterface::class, ...BrokenReference::getReferencedClasses()];
    }

    /**
     * Conditions name them by their codes and UUIDs (ADR 0002), so only deleting one or changing those breaks or mends
     * a reference
     */
    public static function getReferenceFields(): array
    {
        return [ChannelInterface::class => 'code', LocaleInterface::class => 'code'] + BrokenReference::getReferenceFields();
    }

    public function findEntriesReferencing(array $referenced, array $formerValues = []): iterable
    {
        // The store's channels or locales may have changed
        $this->reset();

        // The entries that named a former code may still, and now hold a broken reference
        $ids = $formerValues;
        foreach ($referenced as $record) {
            $id = match (true) {
                $record instanceof MediaItemInterface, $record instanceof EntryInterface => $record->getUuid(),
                $record instanceof ChannelInterface, $record instanceof LocaleInterface, $record instanceof ProductInterface, $record instanceof TaxonInterface => $record->getCode(),
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
        /** @var list<array{block: StoredBlock, field: FieldMetadata, reference: Reference}> $found */
        $found = [];
        foreach ($this->shownBlocks($entry->getBlockTree()->blocks) as $block) {
            foreach ($block->conditions as $condition) {
                foreach ($this->parameters($condition) as [$field, $value]) {
                    yield from $this->deletedInStore($entry, $block, $field, $value);

                    foreach ($this->fieldHandlers->get($field->field)->references($field->field, $value) as $reference) {
                        if ($this->typeLoaders->has($reference->type)) {
                            $found[] = ['block' => $block, 'field' => $field, 'reference' => $reference];
                        }
                    }
                }
            }
        }

        $existing = [];
        foreach (ReferenceLoader::idsByType(array_column($found, 'reference')) as $type => $ids) {
            $loader = $this->typeLoaders->get($type);
            if ($loader instanceof ReferenceTypeLoaderInterface) {
                $existing[$type] = array_fill_keys($loader->existing($ids), true);
            }
        }

        foreach ($found as ['block' => $block, 'field' => $field, 'reference' => $reference]) {
            if (!isset($existing[$reference->type][$reference->id])) {
                yield $this->problem($entry, $block, new TranslatableMessage('setono_sylius_cms.problem.broken_condition_reference', [
                    '%field%' => new TranslatableMessage($field->field->label),
                    '%block%' => $this->blockLabel($block),
                    '%type%' => new TranslatableMessage(sprintf('setono_sylius_cms.reference_type.%s', $reference->type)),
                ]));
            }
        }
    }

    public function reset(): void
    {
        $this->codes = [];
    }

    /**
     * The blocks that render, in tree order: a block of a type the code no longer has isn't shown, nor what's inside it
     *
     * @param list<StoredBlock> $blocks
     *
     * @return \Generator<StoredBlock>
     */
    private function shownBlocks(array $blocks): \Generator
    {
        foreach ($blocks as $block) {
            if ($this->blockTypes->has($block->type)) {
                yield $block;
                yield from $this->shownBlocks($block->children);
            }
        }
    }

    /**
     * The parameter values a condition of a kind the code has holds, each with its field
     *
     * @param array<string, mixed> $condition
     *
     * @return \Generator<array{FieldMetadata, mixed}>
     */
    private function parameters(array $condition): \Generator
    {
        $code = $condition['kind'] ?? null;
        $kind = is_string($code) ? $this->conditionKinds->find($code) : null;
        $params = $condition['params'] ?? null;
        if (null === $kind || !is_array($params)) {
            return;
        }

        foreach ($kind->fields as $name => $field) {
            $value = $params[$name] ?? null;
            if (null !== $value) {
                yield [$field, $value];
            }
        }
    }

    /**
     * A problem for each channel or locale the value names, each once, that the store no longer has
     *
     * @return \Generator<Problem>
     */
    private function deletedInStore(EntryInterface $entry, StoredBlock $block, FieldMetadata $field, mixed $value): \Generator
    {
        $choice = $field->field;
        if (!$choice instanceof Choice) {
            return;
        }

        $type = self::STORE_CHOICES[(string) $choice->choicesProvider] ?? null;
        if (null === $type) {
            return;
        }

        $named = $choice->multiple ? (is_array($value) && array_is_list($value) ? $value : []) : [$value];
        $codes = $this->codes($type);
        foreach (array_unique(array_filter($named, is_string(...))) as $code) {
            if (!in_array($code, $codes, true)) {
                yield $this->problem($entry, $block, new TranslatableMessage(sprintf('setono_sylius_cms.problem.deleted_%s_in_condition', $type), [
                    '%code%' => $code,
                    '%block%' => $this->blockLabel($block),
                ]));
            }
        }
    }

    /**
     * The codes of the store's channels or locales, read once until a write of them is asked about
     *
     * @return list<string>
     */
    private function codes(string $type): array
    {
        if (!isset($this->codes[$type])) {
            $repository = 'channel' === $type ? $this->channelRepository : $this->localeRepository;
            $this->codes[$type] = [];
            foreach ($repository->findAll() as $record) {
                $code = $record->getCode();
                if (null !== $code) {
                    $this->codes[$type][] = $code;
                }
            }
        }

        return $this->codes[$type];
    }

    private function blockLabel(StoredBlock $block): TranslatableMessage
    {
        return new TranslatableMessage($this->blockTypes->get($block->type)->label);
    }

    private function problem(EntryInterface $entry, StoredBlock $block, TranslatableMessage $message): Problem
    {
        return new Problem(self::CODE, Severity::Error, $entry, $message, Fix::inBuilder($entry, $block->key), $block->key);
    }
}
