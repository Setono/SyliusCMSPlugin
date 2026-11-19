# Adding a bindable property

A [binding](../../GLOSSARY.md#shop-pages) lets a field show a property of the host-page context, the channel or the
customer instead of a fixed value, such as the current product's name in a snippet's heading. The properties editors can
bind to are declared in code. A project adds its own in config, when the value is a plain property path, or as a
service with `#[AsBindableProperty]` that implements `Setono\SyliusCMSPlugin\Binding\BindablePropertyInterface`.

## A property path, in config

```yaml
# config/packages/setono_sylius_cms.yaml
setono_sylius_cms:
    bindable_properties:
        taxon:
            app_code: { label: 'app.cms.bindable_property.taxon.code', type: text, path: 'code' }
```

The first key is the context key, `channel` or `customer` the property belongs to, then the property's code. `path` is
a [property path](https://symfony.com/doc/6.4/components/property_access.html) on that object, such as `code` or
`brand.name`. `type` is `text`, `number` or `url`, and decides which fields it can be bound to.

## A service

When the value needs code, write a service. This one is the product's average rating:

```php
// src/CMS/Binding/AverageRatingProperty.php
namespace App\CMS\Binding;

use Setono\SyliusCMSPlugin\Attribute\AsBindableProperty;
use Setono\SyliusCMSPlugin\Binding\BindablePropertyInterface;
use Setono\SyliusCMSPlugin\Binding\BindingType;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Sylius\Component\Core\Model\ProductInterface;

#[AsBindableProperty(
    contextKey: 'product',
    code: 'app_average_rating',
    label: 'app.cms.bindable_property.product.average_rating',
    type: BindingType::Number,
)]
final class AverageRatingProperty implements BindablePropertyInterface
{
    /**
     * Null for a product without accepted reviews, which shows the binding's fallback
     */
    public function resolve(object $subject, object $params, RenderContext $context): ?float
    {
        if (!$subject instanceof ProductInterface || $subject->getAcceptedReviews()->isEmpty()) {
            return null;
        }

        return round((float) $subject->getAverageRating(), 1);
    }
}
```

- **`$subject`** is the context key's object, here the product of the page, or the channel or the customer.
- **Return `null` or `''`** when there's nothing to show, and the field shows the fallback the editor typed.
- **Read the channel, locale and currency from the `RenderContext`**, as the shipped price property does to format a
  price in the shopper's currency.
- **`type`** is a `Setono\SyliusCMSPlugin\Binding\BindingType` case, `Text`, `Number` or `Url`. Text and textarea
  fields, and tokens in rich text, take text and numbers, number fields take numbers, and a link's URL takes URLs.
- **`params`** names a parameters class, as for a [source](sources.md#the-parameters), when editors choose something for
  the binding, such as which product attribute to show. Without it, `resolve()` gets a
  `Setono\SyliusCMSPlugin\Attribute\NoParams`.

A binding is stored as `<context key>.<code>`, such as `product.app_average_rating`, so prefix the code. Autoconfiguration
registers the service. Add the label to `translations/messages.en.yaml`.

The shipped bindable properties are listed in [Slots](../spec/slots.md#bindings).
