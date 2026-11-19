# Adding a link target

A link target is what a link can lead to. The plugin ships `entry`, `product`, `taxon`, `media` and `url`, and editors
pick one in every Link field and in rich text. A project adds its own as a service with `#[AsLinkTarget]` that
implements `Setono\SyliusCMSPlugin\Link\LinkTargetInterface`.

This guide adds the shop's physical stores, kept outside Sylius, which editors pick by name.

```php
// src/CMS/Link/StoreLinkTarget.php
namespace App\CMS\Link;

use Setono\SyliusCMSPlugin\Attribute\AsLinkTarget;
use Setono\SyliusCMSPlugin\Link\LinkTargetInterface;
use Setono\SyliusCMSPlugin\Link\ResolvedLink;
use Setono\SyliusCMSPlugin\Rendering\RenderContext;

#[AsLinkTarget(code: 'app_store', label: 'app.cms.link_target.store')]
final class StoreLinkTarget implements LinkTargetInterface
{
    // In a real project, these come from your own repository or API
    private const STORES = ['copenhagen' => 'Copenhagen', 'aarhus' => 'Aarhus'];

    public function resolve(string $ref, RenderContext $context): ?ResolvedLink
    {
        $name = self::STORES[$ref] ?? null;

        return null === $name ? null : new ResolvedLink('app_store', $ref, sprintf('https://stores.example.com/%s', $ref), $name);
    }

    public function search(string $query, string $localeCode): iterable
    {
        foreach (self::STORES as $ref => $name) {
            if (str_contains(strtolower($name), strtolower($query))) {
                yield ['ref' => $ref, 'name' => $name, 'thumbnail' => null];
            }
        }
    }
}
```

- **The ref** is what a link stores to find its record again, such as a code or a UUID, never a URL that can change.
  Pick one that survives a [transfer](../../GLOSSARY.md#transfer) to another environment.
- **`resolve()`** turns a ref into a `ResolvedLink`: the target's code, the ref, the URL and the record's name in the
  shopper's locale, which a link with an empty label shows. Return `null` when the record is gone or the shopper may not
  see it: the link doesn't render, and the admin flags it. The `RenderContext` has the channel and the locale.
- **`search()`** answers the builder's picker, best match first, with a `ref`, a `name` and a `thumbnail` URL or `null`.
- **`translatableRef: true`** stores a ref per locale, as `url` does, for a target whose record differs by locale. The
  plugin picks the current locale's ref, through the fallback chain, so `resolve()` always gets one string.

Every link can add an anchor to its URL. Autoconfiguration registers the service. Add the label to
`translations/messages.en.yaml`:

```yaml
app:
    cms:
        link_target:
            store: Store
```

A Link field offers every target unless it lists some: `#[Link(label: '…', targets: ['url', 'app_store'])]`.

The test application's copy of this target is `tests/Application/Link/StoreLinkTarget.php`.
