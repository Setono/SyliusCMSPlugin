# Bindings use declared properties

A binding shows a property of the host-page context, the channel or the customer, such as the current product's name, in a field or as a token inside rich text. Editors can only bind properties that are declared in code for each kind of context object, with a label and a type. Developers can declare more. The plugin ships a "Product attribute" property, where the editor picks one of the store's attributes from a list, so attributes need no declaration each. Editors can't enter a property path.

We chose this over letting editors reach any property, such as `product.variants.first.price`, for the same reasons there's no admin-editable Twig in v2.0:

- Arbitrary paths expose internals and data that were never meant to be shown.
- They break silently when a Sylius model changes.
- Editors couldn't discover which paths exist.

With declared properties, the picker can list exactly what fits a field's type and the snippet's declared context.

The cost is that showing a new property needs a developer to declare it.

Customer properties are bindable too, such as the customer's first name. They waited for the caching design, because personalised output must never end up in a page cached for everyone. ADR 0021 settled that: v2.0 caches no storefront output, so customer bindings ship.
