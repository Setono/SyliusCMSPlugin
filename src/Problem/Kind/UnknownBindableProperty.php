<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Problem\Kind;

use Setono\SyliusCMSPlugin\Binding\StoredValueBindings;
use Setono\SyliusCMSPlugin\Model\EntryInterface;
use Setono\SyliusCMSPlugin\Problem\Fix;
use Setono\SyliusCMSPlugin\Problem\Problem;
use Setono\SyliusCMSPlugin\Problem\ProblemKindInterface;
use Setono\SyliusCMSPlugin\Problem\Severity;
use Setono\SyliusCMSPlugin\Registry\BindablePropertyRegistryInterface;
use Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface;
use Setono\SyliusCMSPlugin\Tree\TreeValues;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * A field, in a block or in the tree's layout, bound to a property the code no longer declares ("<context key>.<code>"
 * the bindable property registry doesn't have), a warning: its whole value, a Link's ref or a token in rich text. It
 * shows its fallback, and publish refuses it. It's fixed in the builder, by binding the field to another property or
 * unbinding it, or by bringing the property back. Once a field, value and property. See "Bindings" in
 * docs/spec/slots.md and "Problems" in docs/spec/admin.md
 */
final readonly class UnknownBindableProperty implements ProblemKindInterface
{
    public const CODE = 'unknown_bindable_property';

    public function __construct(
        private TreeValues $treeValues,
        private ContentTypeRegistryInterface $contentTypes,
        private StoredValueBindings $bindings,
        private BindablePropertyRegistryInterface $properties,
    ) {
    }

    /**
     * A layout that was removed or disabled is another kind's problem, and the default layout ignores its values, so
     * only the fields the tree renders are checked
     */
    public function findIn(EntryInterface $entry): iterable
    {
        foreach ($this->treeValues->inTree($entry->getBlockTree(), $this->contentTypes->getByEntry($entry)->code) as $value) {
            $unknown = [];
            foreach ($this->bindings->of($value) as $binding) {
                if (!$this->properties->has($binding->property)) {
                    $unknown[$binding->property] = true;
                }
            }

            $field = $value->field;
            foreach (array_keys($unknown) as $property) {
                $property = (string) $property;

                if (null === $value->block || null === $value->blockType) {
                    yield new Problem(
                        self::CODE,
                        Severity::Warning,
                        $entry,
                        new TranslatableMessage('setono_sylius_cms.problem.unknown_layout_bindable_property', ['%field%' => new TranslatableMessage($field->field->label), '%property%' => $property]),
                        Fix::inBuilder($entry, null, $value->locale),
                        null,
                        $field->name,
                        $value->locale,
                    );

                    continue;
                }

                yield new Problem(
                    self::CODE,
                    Severity::Warning,
                    $entry,
                    new TranslatableMessage('setono_sylius_cms.problem.unknown_bindable_property', [
                        '%field%' => new TranslatableMessage($field->field->label),
                        '%block%' => new TranslatableMessage($value->blockType->label),
                        '%property%' => $property,
                    ]),
                    Fix::inBuilder($entry, $value->block->key, $value->locale),
                    $value->block->key,
                    $field->name,
                    $value->locale,
                );
            }
        }
    }
}
