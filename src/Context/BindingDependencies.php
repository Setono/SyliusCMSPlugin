<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Context;

use Setono\SyliusCMSPlugin\Binding\BoundValue;
use Setono\SyliusCMSPlugin\Binding\StoredBinding;
use Setono\SyliusCMSPlugin\Field\FieldHandlerLocator;
use Setono\SyliusCMSPlugin\Field\InnerBindingFieldHandlerInterface;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Registry\BindablePropertyMetadata;
use Setono\SyliusCMSPlugin\Registry\BindablePropertyRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\StoredValue;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The bindings that read a context key, in the blocks' and the layout's fields: a bound field's, a Link's ref and the
 * tokens in rich text. Each needs the key its property reads, which the channel's and the customer's never do. A binding
 * of a property the code no longer has needs the key it names. See "Snippets and their context" in docs/spec/slots.md
 *
 * @internal
 */
final readonly class BindingDependencies implements ContextDependencyFinderInterface
{
    public function __construct(
        private TreeValues $treeValues,
        private ContentTypeRegistryInterface $contentTypes,
        private FieldHandlerLocator $fieldHandlers,
        private BindablePropertyRegistryInterface $properties,
    ) {
    }

    public function findIn(EntryInterface $workingCopy): iterable
    {
        $found = [];
        foreach ($this->treeValues->inTree($workingCopy->getBlockTree(), $this->contentTypes->getByEntry($workingCopy)->code) as $value) {
            foreach ($this->bindings($value) as $binding) {
                $needs = $this->needs($binding);
                $id = implode("\0", [$value->block?->key, $value->field->name, $binding->property]);
                if ([] === $needs || isset($found[$id])) {
                    continue;
                }

                $found[$id] = true;

                yield new ContextDependency($this->name($binding, $value), $needs, block: $value->block?->key);
            }
        }
    }

    /**
     * @return iterable<StoredBinding>
     */
    private function bindings(StoredValue $value): iterable
    {
        $binding = BoundValue::of($value->field, $value->value);
        if (null !== $binding) {
            return [$binding];
        }

        $handler = $this->fieldHandlers->get($value->field->field);

        return $handler instanceof InnerBindingFieldHandlerInterface && !$value->isNotByLocale() ? $handler->bindings($value->field->field, $value->value) : [];
    }

    /**
     * @return list<string>
     */
    private function needs(StoredBinding $binding): array
    {
        // Read from the binding, so one of a property the code no longer has still needs its key
        return BindablePropertyMetadata::needsFor($binding->contextKey());
    }

    private function name(StoredBinding $binding, StoredValue $value): TranslatableMessage
    {
        $property = $this->properties->has($binding->property) ? new TranslatableMessage($this->properties->get($binding->property)->label) : $binding->property;

        if (null === $value->blockType) {
            return new TranslatableMessage('setono_sylius_cms.context.layout_binding', ['%binding%' => $property], 'validators');
        }

        return new TranslatableMessage('setono_sylius_cms.context.binding', ['%binding%' => $property, '%block%' => new TranslatableMessage($value->blockType->label)], 'validators');
    }
}
