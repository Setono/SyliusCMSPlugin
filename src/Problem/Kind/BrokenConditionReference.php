<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Condition\StoredCondition;
use Setono\SyliusCMSPlugin\Field\Choice;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\Reference;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\BrokenReferences;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ReferenceProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BlockTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ConditionKindRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\FieldMetadata;
use Setono\SyliusCMSPlugin\Targeting\ChannelChoicesProvider;
use Setono\SyliusCMSPlugin\Targeting\LocaleChoicesProvider;
use Setono\SyliusCMSPlugin\Tree\StoredBlock;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Sylius\Component\Channel\Model\ChannelInterface;
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
 * entries that may name it counted again. See "Deleted records in conditions" in docs/spec/targeting.md and "Problems"
 * in docs/spec/admin.md
 */
final class BrokenConditionReference implements ReferenceProblemKindInterface, ResetInterface
{
    public const CODE = 'broken_condition_reference';

    /**
     * @var array<class-string, array{repository: RepositoryInterface<ChannelInterface>|RepositoryInterface<LocaleInterface>, message: string}>
     *      what a Choice of the store's channels or locales needs, by its choices provider: where the records are read
     *      from, and the message of a problem naming one the store no longer has
     */
    private readonly array $storeChoices;

    /** @var array<class-string, list<string>> the codes of the store's channels and locales, by choices provider, as they were read */
    private array $codes = [];

    /**
     * @param RepositoryInterface<ChannelInterface> $channelRepository
     * @param RepositoryInterface<LocaleInterface> $localeRepository
     */
    public function __construct(
        private readonly TreeValues $treeValues,
        private readonly BlockTypeRegistryInterface $blockTypes,
        private readonly ConditionKindRegistryInterface $conditionKinds,
        private readonly FieldHandlerLocator $fieldHandlers,
        private readonly BrokenReferences $brokenReferences,
        RepositoryInterface $channelRepository,
        RepositoryInterface $localeRepository,
    ) {
        $this->storeChoices = [
            ChannelChoicesProvider::class => ['repository' => $channelRepository, 'message' => 'setono_sylius_cms.problem.deleted_channel_in_condition'],
            LocaleChoicesProvider::class => ['repository' => $localeRepository, 'message' => 'setono_sylius_cms.problem.deleted_locale_in_condition'],
        ];
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

        return $this->brokenReferences->entriesReferencing($referenced, $formerValues);
    }

    public function findIn(EntryInterface $entry): iterable
    {
        /** @var list<array{block: StoredBlock, field: FieldMetadata}> $found the block and parameter of each reference */
        $found = [];
        /** @var list<Reference> $references */
        $references = [];
        foreach ($this->treeValues->shownBlocks($entry->getBlockTree()->blocks) as $block) {
            foreach ($block->conditions as $condition) {
                foreach ($this->parameters($condition) as [$field, $value]) {
                    yield from $this->deletedInStore($entry, $block, $field, $value);

                    foreach ($this->fieldHandlers->get($field->field)->references($field->field, $value) as $reference) {
                        $found[] = ['block' => $block, 'field' => $field];
                        $references[] = $reference;
                    }
                }
            }
        }

        foreach ($this->brokenReferences->broken($references) as $index => $reference) {
            ['block' => $block, 'field' => $field] = $found[$index];

            yield $this->problem($entry, $block, new TranslatableMessage('setono_sylius_cms.problem.broken_condition_reference', [
                '%field%' => new TranslatableMessage($field->field->label),
                '%block%' => $this->blockLabel($block),
                '%type%' => new TranslatableMessage(sprintf('setono_sylius_cms.reference_type.%s', $reference->type)),
            ]));
        }
    }

    public function reset(): void
    {
        $this->codes = [];
    }

    /**
     * The parameter values a condition of a kind the code has holds, each with its field
     *
     * @return \Generator<array{FieldMetadata, mixed}>
     */
    private function parameters(mixed $value): \Generator
    {
        $condition = StoredCondition::fromValue($value);
        $kind = null === $condition ? null : $this->conditionKinds->find($condition->kind);
        if (null === $condition || null === $kind) {
            return;
        }

        foreach ($kind->fields as $name => $field) {
            $param = $condition->params[$name] ?? null;
            if (null !== $param) {
                yield [$field, $param];
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
        if (!$choice instanceof Choice || !isset($this->storeChoices[(string) $choice->choicesProvider])) {
            return;
        }

        $provider = (string) $choice->choicesProvider;
        $named = $choice->multiple ? (is_array($value) && array_is_list($value) ? $value : []) : [$value];
        $codes = $this->codes($provider);
        foreach (array_unique(array_filter($named, is_string(...))) as $code) {
            if (!in_array($code, $codes, true)) {
                yield $this->problem($entry, $block, new TranslatableMessage($this->storeChoices[$provider]['message'], [
                    '%code%' => $code,
                    '%block%' => $this->blockLabel($block),
                ]));
            }
        }
    }

    /**
     * The codes of the store's records a Choice of its channels or locales picks from, read once until a write of them
     * is asked about
     *
     * @param class-string $provider
     *
     * @return list<string>
     */
    private function codes(string $provider): array
    {
        if (!isset($this->codes[$provider])) {
            $this->codes[$provider] = [];
            foreach ($this->storeChoices[$provider]['repository']->findAll() as $record) {
                $code = $record->getCode();
                if (null !== $code) {
                    $this->codes[$provider][] = $code;
                }
            }
        }

        return $this->codes[$provider];
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
