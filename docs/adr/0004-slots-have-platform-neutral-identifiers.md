# Slots have platform-neutral identifiers

Every slot is declared in code with its own identifier, such as `product.after_description`. An adapter ties it to the place on the platform where it renders. On Sylius 1.14 that place is a template event. Placements store the slot identifier and never a Sylius template event name.

We chose this over exposing Sylius template events directly as slots. Sylius 2 replaces template events with Twig Hooks, which have different names, nesting and context. Stored placements that named 1.14 events would all need remapping in the port. With our own identifiers, only the adapters change.

The same declaration also lets a slot provide context its template event doesn't pass, through a resolver. For example, the taxon page's grid events don't carry `taxon`.

The cost is a layer of indirection. Developers have to declare a slot before editors can fill it. A template event the plugin doesn't declare a slot for stays invisible to editors.
