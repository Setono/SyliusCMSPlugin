# Switching off what ships

A project switches off the shipped items it doesn't want in `config/packages/setono_sylius_cms.yaml`. Content that
already uses them keeps working, so switching one off never breaks a page.

```yaml
setono_sylius_cms:
    disabled:
        block_types: ['video', 'downloads']   # block type codes
        layouts: ['page.standard']            # "<content type code>.<layout code>"
        sources: ['bestsellers']              # source codes
        presets: ['three_usps']               # code preset codes
        content_types: ['faq_item', 'faq_category']

    # A content type's default layout can't be switched off, so Page gets another one first: a layout of your own
    content_types:
        page:
            default_layout: 'landing'
```

| Key | What happens |
| --- | --- |
| `block_types` | Editors no longer find the block type in the picker. Blocks of it already in block trees keep rendering, and presets that use it are marked in the Presets tab. |
| `layouts` | Editors no longer pick the layout, and its entries render in their content type's default layout, which can't be switched off. |
| `sources` | Editors aren't offered the source. Lists already filled by it keep resolving through it. |
| `presets` | Editors aren't offered the code preset. |
| `content_types` | The content type is hidden from the admin, the builder, sources and routing, and its columns stay mapped. Page and Snippet can't be switched off. Switch off a collection's entries and its categories together, such as `blog_post` and `blog_category`. |

The codes of the shipped block types, sources and code presets are listed in [Blocks](../spec/blocks.md#block-types),
[Sources](../spec/sources.md) and [Shipped code presets](../spec/layouts-and-presets.md#shipped-code-presets). A
content type code that no content type has fails the container build, as does switching off a default layout. An
unknown block type, layout, source or preset code is ignored, so check the spelling.

## Off until you opt in

**The Embed code block** prints raw HTML and scripts in the shop, so it's off until a project accepts that risk
([ADR 0018](../adr/0018-raw-html-is-an-opt-in-embed-code-block.md)):

```yaml
setono_sylius_cms:
    embed_code: true
```

## The stylesheet and the carousel script

```yaml
setono_sylius_cms:
    assets: false
```

stops loading the shipped stylesheet and carousel script on every shop page, for a project that bundles both into its own
build ([Styling](styling.md)).

## A stock slot

Each stock slot renders through a block of its own on a Sylius template event, named `setono_sylius_cms.slot.<slot>`.
Switch one off in your own `sylius_ui` config, as you would a block of Sylius's:

```yaml
# config/packages/sylius_ui.yaml
sylius_ui:
    events:
        sylius.shop.layout.footer:
            blocks:
                setono_sylius_cms.slot.shop.footer: { enabled: false }
```

The slot's placements stay, and show again once it's switched back on. To move a stock slot to another template event,
or give it another priority, change its `template_event` instead:

```yaml
setono_sylius_cms:
    slots:
        product.sidebar:
            template_event: { name: 'sylius.shop.product.show.right_sidebar', priority: 50 }
```

The stock slots and their template events are listed in [Stock slots](../spec/slots.md#stock-slots). `cap` limits how
many placements a slot shows: `slots.shop.top.cap: 1`.

## Replacing a service

Several services sit behind an interface that a project points at its own service, such as:

| Interface | Replace it to |
| --- | --- |
| `Setono\SyliusCMSPlugin\Media\ImageProviderInterface` | Serve images from an image CDN instead of LiipImagine. `ImageFormatsInterface` says which formats it serves. |
| `Setono\SyliusCMSPlugin\Media\MediaUrlGeneratorInterface` | Build the URLs of a media storage other than the default, such as S3. |
| `Setono\SyliusCMSPlugin\Bestsellers\BestsellersCalculatorInterface` | Score bestsellers from analytics data instead of completed orders. |
| `Setono\SyliusCMSPlugin\Preview\HostPageUrlGeneratorInterface` | Give a slot of your own a page to preview its snippets on. |

```yaml
# config/services.yaml
services:
    Setono\SyliusCMSPlugin\Bestsellers\BestsellersCalculatorInterface: '@App\CMS\AnalyticsBestsellersCalculator'
```

To change what a service does rather than replace it, decorate it, as the [caching cookbook](../cookbook/caching.md)
does. The interfaces are in [Extending](../spec/extending.md).

## Copy, don't extend

Projects never add fields to a shipped block type, because its version and upgraders belong to the plugin. To change one,
register a block type of your own with its own code, switch the shipped one off, and keep or override its template
([Overriding templates](overriding-templates.md)).
