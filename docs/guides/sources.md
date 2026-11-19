# Adding a source

A [source](../../GLOSSARY.md#content-structure) fills a list field when content is shown, such as a Products block with
"the newest products" or "the products in a taxon". A project adds its own as a service with `#[AsSource]` that
implements `Setono\SyliusCMSPlugin\Source\SourceInterface`. Editors then pick it in every list field of the kind it
lists, unless that field allows only some sources.

This guide adds "On sale": the products whose price is below their original price, optionally only those in a taxon.

## The parameters

What editors fill in for the source is a class whose public typed properties carry field attributes, as a block type's
fields do:

```php
// src/CMS/Source/OnSaleParams.php
namespace App\CMS\Source;

use Setono\SyliusCMSPlugin\Field\Taxon;
use Sylius\Component\Core\Model\TaxonInterface;

final class OnSaleParams
{
    /** Only the products in this taxon, or all of them when it's empty */
    #[Taxon(label: 'app.cms.field.on_sale.taxon')]
    public ?TaxonInterface $taxon = null;
}
```

A source without parameters leaves `params` out, and gets a `Setono\SyliusCMSPlugin\Attribute\NoParams`.

## The source

```php
// src/CMS/Source/OnSaleSource.php
namespace App\CMS\Source;

use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Setono\SyliusCMSPlugin\Attribute\AsSource;
use Setono\SyliusCMSPlugin\Query\VisibleProductsQueryInterface;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;
use Setono\SyliusCMSPlugin\Source\SourceInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductInterface;

#[AsSource(code: 'app_on_sale', label: 'app.cms.source.on_sale', of: ProductInterface::class, params: OnSaleParams::class)]
final readonly class OnSaleSource implements SourceInterface
{
    public function __construct(private VisibleProductsQueryInterface $visibleProducts)
    {
    }

    public function resolve(object $params, RenderContext $context): AdapterInterface
    {
        if (!$params instanceof OnSaleParams) {
            throw new \InvalidArgumentException(sprintf('The on sale source takes %s, got %s', OnSaleParams::class, $params::class));
        }

        // The products the shopper may see in the channel and locale, with the root alias "product"
        $queryBuilder = $this->visibleProducts->createQueryBuilder($context)
            ->andWhere(sprintf(
                'EXISTS (SELECT app_pricing.id FROM %s app_pricing INNER JOIN app_pricing.productVariant app_variant WHERE app_variant.product = product AND app_variant.enabled = true AND app_pricing.channelCode = :app_channel AND app_pricing.originalPrice > app_pricing.price)',
                ChannelPricingInterface::class,
            ))
            ->setParameter('app_channel', $context->channel->getCode())
        ;

        if (null !== $params->taxon) {
            $queryBuilder
                ->innerJoin('product.productTaxons', 'app_product_taxon', 'WITH', 'app_product_taxon.taxon = :app_taxon')
                ->setParameter('app_taxon', $params->taxon)
            ;
        }

        // A stable order that ends in a unique tie-breaker, so pages never overlap
        $queryBuilder->addOrderBy('product.createdAt', 'DESC')->addOrderBy('product.id', 'DESC');

        return new QueryAdapter($queryBuilder, false);
    }
}
```

`#[AsSource]` takes:

| Option | Meaning |
| --- | --- |
| `code`, `label` | A unique code, prefixed such as `app_on_sale`, and a translation key. The code is stored in content. |
| `of` | What it lists: `ProductInterface::class`, `TaxonInterface::class`, `EntryInterface::class` for entries of any content type, or one content type's model. Only list fields of that kind offer it. |
| `needs` | The context keys it needs, such as `['product']` for "related to this product". It's offered only where they're available. |
| `params` | Its parameters class. |

Autoconfiguration registers it, and autowiring gives it its dependencies, as for any service.

## The rules a source follows

- **It returns a Pagerfanta adapter, and takes no limit or offset.** The list field pages it: a Products block takes the
  first slice it shows, and a paginated Entries block a page. Return a `QueryAdapter` so paging and counting happen in
  SQL, and an `ArrayAdapter` for an empty list.
- **It builds on the visible queries.** `VisibleProductsQueryInterface`, `VisibleTaxonsQueryInterface` and
  `VisibleEntriesQueryInterface` (all in `Setono\SyliusCMSPlugin\Query`) keep only what the shopper may see in the channel
  and locale. Their root aliases are `product`, `taxon` and `entry`, and they have no `ORDER BY`. Prefix your own aliases
  and parameters, such as `app_`, so they never clash with theirs.
- **Its order is stable and ends in a unique tie-breaker**, such as the ID, so pages never overlap or skip items.
- **It reads the channel, locale, customer and time only from the `RenderContext`**, which the builder's preview sets as
  editors choose.

An entry source that lists one content type orders it as the content type's `order` says, with
`$contentTypes->get('store_location')->order->applyTo($queryBuilder, 'entry')`, where `$contentTypes` is the
`Setono\SyliusCMSPlugin\Registry\ContentTypeRegistryInterface` service. The full contract is in
[Sources](../spec/sources.md).

## Labels

```yaml
# translations/messages.en.yaml
app:
    cms:
        source:
            on_sale: On sale
        field:
            on_sale:
                taxon: Only in this taxon
```

`setono_sylius_cms.disabled.sources` switches a shipped source off ([Switching off what ships](switching-off.md)).
