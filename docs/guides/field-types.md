# Adding a field type and its field control

A [field type](../../GLOSSARY.md#content-structure) is a pair, like a Symfony constraint and its validator: an attribute
class that fields are declared with, and a handler service that describes, checks and hydrates its values. Editors fill
a field of a project's own field type in its [field control](../../GLOSSARY.md#content-structure), a custom element the
builder loads from the project's script.

Use the shipped field types when they fit ([Shipped field types](../spec/extending.md#shipped-field-types)): a block type
made of them needs no JavaScript. This guide adds a star rating, from 1 to 5.

## The attribute

```php
// src/CMS/Field/Rating.php
namespace App\CMS\Field;

use Setono\SyliusCMSPlugin\Attribute\FieldType;
use Setono\SyliusCMSPlugin\Field\Field;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
#[FieldType(code: 'app_rating', translatable: false, bindable: [], control: 'app-rating')]
final class Rating extends Field
{
    public function __construct(
        string $label,
        public readonly int $max = 5,
        ?string $help = null,
        bool $required = false,
    ) {
        parent::__construct($label, $help, $required);
    }
}
```

- **It extends `Setono\SyliusCMSPlugin\Field\Field`**, whose options are `label`, `help`, `required` and `translatable`.
  Its own options, such as `max`, are public readonly properties.
- **`#[FieldType]`** declares its `code`, unique and never changed once content uses it, whether its fields are
  `translatable` by default, which [binding types](bindable-properties.md) it accepts (`bindable`, a list of
  `BindingType` cases, empty for none) and its field control's tag name (`control`), which is required for a project's
  own field type.
- **The handler** is the service whose id is the attribute's class name plus `Handler`, here
  `App\CMS\Field\RatingHandler`. Override `handledBy()` to name another.

## The handler

```php
// src/CMS/Field/RatingHandler.php
namespace App\CMS\Field;

use Setono\SyliusCMSPlugin\Field\Field;
use Setono\SyliusCMSPlugin\Field\FieldHandlerInterface;
use Setono\SyliusCMSPlugin\Field\HydrationContext;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Stores a rating as a whole number from 1 to the field's max
 */
final class RatingHandler implements FieldHandlerInterface
{
    public function schema(Field $field): array
    {
        return [
            'type' => 'integer',
            'minimum' => 1,
            'maximum' => self::max($field),
            // What the field control reads from .field['x-cms-options']
            'x-cms-options' => ['max' => self::max($field)],
        ];
    }

    public function hydrate(Field $field, mixed $value, HydrationContext $context): ?int
    {
        return self::fits($field, $value) ? $value : null;
    }

    public function references(Field $field, mixed $value): iterable
    {
        return [];
    }

    public function validate(Field $field, mixed $value, ExecutionContextInterface $context): void
    {
        if (null !== $value && !self::fits($field, $value)) {
            $context->buildViolation('app.cms.rating.out_of_range')
                ->setParameter('%max%', (string) self::max($field))
                ->addViolation()
            ;
        }
    }

    /**
     * @phpstan-assert-if-true int $value
     */
    private static function fits(Field $field, mixed $value): bool
    {
        return is_int($value) && $value >= 1 && $value <= self::max($field);
    }

    private static function max(Field $field): int
    {
        return $field instanceof Rating ? $field->max : 5;
    }
}
```

| Method | Does |
| --- | --- |
| `schema()` | The JSON Schema of one value, one locale's for a translatable field. The plugin adds the `x-cms-*` keywords the builder reads, such as the label, and the handler adds its own options in `x-cms-options`. |
| `hydrate()` | Turns a stored value into what templates get, lazily. `null` means it's missing, also for a stored value that doesn't fit. Records it refers to are in `$context->references`, loaded before rendering. |
| `references()` | The records a value refers to, as `Setono\SyliusCMSPlugin\Field\Reference`s, such as `new Reference('media_item', $uuid, true)`. They're loaded with every other reference on the page, kept in the media usage index, and brought along by an export when the third argument says it depends on them. |
| `validate()` | Adds a violation for a value that doesn't fit, which refuses the publish. Messages are in the `validators` domain. |

Autoconfiguration tags a service that implements `FieldHandlerInterface`, so the handler can take dependencies, such as
the translator to translate its field control's own strings into `x-cms-options`.

## The field control

A field control is a custom element. The builder sets four properties on it, and listens for a `cms-change` event:

```js
// public/cms/rating.js
class RatingControl extends HTMLElement {
    #value = null;
    #field = null;

    get value() {
        return this.#value;
    }

    set value(value) {
        this.#value = value ?? null;
        this.#render();
    }

    get field() {
        return this.#field;
    }

    set field(field) {
        this.#field = field;
        this.#render();
    }

    // A rating is the same in every locale, and needs nothing of the builder
    locale = null;
    builder = null;

    connectedCallback() {
        this.#render();
    }

    #render() {
        if (!this.isConnected || this.#field === null) {
            return;
        }

        const select = document.createElement('select');
        select.setAttribute('aria-label', this.#field['x-cms-label']);
        select.append(new Option('–', ''));
        for (let stars = 1; stars <= (this.#field['x-cms-options']?.max ?? 5); stars++) {
            select.append(new Option('★'.repeat(stars), String(stars)));
        }
        select.value = this.#value === null ? '' : String(this.#value);
        select.addEventListener('change', () => {
            this.#value = select.value === '' ? null : Number(select.value);
            this.dispatchEvent(new CustomEvent('cms-change', { bubbles: true, detail: { value: this.#value } }));
        });

        this.replaceChildren(select);
    }
}

customElements.define('app-rating', RatingControl);
```

- **`.value`** is the stored value, the current locale's for a translatable field, or `null`. **`.field`** is the
  field's schema, with its label in `x-cms-label` and the handler's options in `x-cms-options`. **`.locale`** is the
  locale being edited, and **`.builder`** has `search(kind, query)`, `pickMedia({kinds, multiple})` and
  `trans(key, params)`.
- **Dispatch a bubbling `cms-change` event** with the new value in `detail.value` whenever it changes. `null`, `''` or
  `[]` removes the value. The builder doesn't set back a value the element just gave it, so the element keeps its focus.

Load the script in the builder, as an asset path that Symfony's `asset()` resolves:

```yaml
# config/packages/setono_sylius_cms.yaml
setono_sylius_cms:
    builder:
        scripts: ['cms/rating.js']
```

Scripts load as ES modules before the builder app, in the order listed. A project whose build versions its files lists
the asset path its manifest knows, such as `build/app/cms/controls.js`.

## Using it

```php
// src/CMS/Block/Review.php
namespace App\CMS\Block;

use App\CMS\Field\Rating;
use Setono\SyliusCMSPlugin\Attribute\BlockType;
use Setono\SyliusCMSPlugin\Field\Textarea;

#[BlockType(code: 'app_review', label: 'app.cms.block_type.review', template: 'cms/block/review.html.twig')]
final class Review
{
    #[Textarea(label: 'app.cms.field.quote')]
    public ?string $quote = null;

    #[Rating(label: 'app.cms.field.rating', required: true)]
    public ?int $rating = null;
}
```

```twig
{# templates/cms/block/review.html.twig #}
{% set rating = cms_value(block, 'rating') %}
<figure class="app-review{{ view.styleClass ? ' ' ~ view.styleClass }}">
    {% if rating is not null %}
        <p class="app-review__rating" role="img" aria-label="{{ rating }}">{% for star in 1..rating %}★{% endfor %}</p>
    {% endif %}
    <blockquote>{{ cms_field(block, 'quote') }}</blockquote>
</figure>
```

The container build checks every field type that the block types, layouts, content types, sources and condition kinds
use: a code used twice, a code that isn't `^[a-z][a-z0-9_]*$`, and a project's own field type without a valid `control`
fail it.

## More

The test application's opening hours field type stores an object with a media item in it, translates its field
control's strings, and picks the media item with `.builder.pickMedia()`: `tests/Application/Field/OpeningHours.php`,
`OpeningHoursHandler.php` and `tests/Application/public/cms/opening-hours.js`. The full contract is in
[Field types](../spec/extending.md#field-types) and [Field controls](../spec/extending.md#field-controls).
