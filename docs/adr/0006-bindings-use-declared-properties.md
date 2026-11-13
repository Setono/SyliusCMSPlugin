# Bindings use declared properties

A binding shows a property of the host-page context or the channel, such as the current product's name, in a field or as a token inside rich text. Editors can only bind properties that are declared in code for each kind of context object, with a label and a type. Developers can declare more, such as product attributes. Editors can't enter a property path.

We chose this over letting editors reach any property, such as `product.variants.first.price`, for the same reasons there's no admin-editable Twig in v2.0:

- Arbitrary paths expose internals and data that were never meant to be shown.
- They break silently when a Sylius model changes.
- Editors couldn't discover which paths exist.

With declared properties, the picker can list exactly what fits a field's type and the snippet's declared context.

The cost is that showing a new property needs a developer to declare it.

Customer properties aren't bindable yet. Personalised output must never end up in a page cached for everyone, so customer bindings wait for the caching design.
