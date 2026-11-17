<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Field;

use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Binding\BindingType;

/**
 * A link to an entry, a product, a taxon, a URL or a project's own link target, with an optional anchor and label.
 * Hydrated as ?Setono\SyliusCMSPlugin\Value\Link, which cms_link() resolves. Its translatable picks one of three shapes:
 * null, the default, keeps the label per locale, and the ref too for a target whose ref is translatable, such as a URL;
 * false keeps one link for every locale, and true a whole link per locale. See "Links" in docs/spec/content-model.md
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'link', bindable: [BindingType::Url])]
final class Link extends Field
{
    /**
     * @param list<string>|null $targets the codes of the link targets it links to, in the order the builder offers
     *                                   them, or null for every one
     */
    public function __construct(
        string $label,
        public readonly ?array $targets = null,
        ?string $help = null,
        bool $required = false,
        ?bool $translatable = null,
    ) {
        if ([] === $targets) {
            throw new \InvalidArgumentException('A Link field links to at least one link target. Leave "targets" at null for every one');
        }

        parent::__construct($label, $help, $required, $translatable);
    }
}
