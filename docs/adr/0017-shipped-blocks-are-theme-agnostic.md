# Shipped blocks are theme-agnostic

The block types the plugin ships render plain semantic markup with their own `cms-` prefixed class names, not the markup of any CSS framework. A small prebuilt stylesheet handles only layout and mechanics, such as grids, columns, carousels, aspect ratios and spacing. It inherits fonts and colours from the shop, exposes CSS custom properties for tuning, and sits in its own cascade layer, so any theme CSS wins without specificity fights. Carousels scroll with CSS scroll-snap, and a small dependency-free script adds arrows, dots and autoplay.

Editors don't get free design controls. A block type can offer **block styles**, named looks such as "Dark" or "Outlined" that the plugin or a project declares in code, each mapped to a CSS class.

Product grids and carousels are the exception to our own markup: each product renders through the shop's own product card, `@SyliusShop/Product/_box.html.twig` on 1.14, so CMS product lists match the shop's other lists on every theme.

We chose this because most shops that install the plugin run a custom theme, not the stock Semantic UI shop, and the plugin will be ported to Sylius 2.x, whose shop uses Bootstrap 5. Markup tied to one framework would look wrong on most themes and be rewritten in the port. Unstyled markup alone would leave carousels and columns broken on a stock shop until someone writes CSS. Named styles keep pages on-brand and keep presentation values out of the stored block tree.

## Considered Options

- **Semantic UI markup.** It matches the 1.14 default shop out of the box, but custom themes get classes they don't use, and every template is rewritten for Bootstrap 5 in the 2.x port.
- **Unstyled HTML with no CSS.** The cleanest for agencies, but shipped blocks look broken on a stock shop.
- **Two template sets**, Semantic UI and Bootstrap 5. A native look on both, at double the template maintenance.
- **Free design controls** such as colour pickers, spacing and font size, as in Elementor. Flexible for merchants, but pages drift off-brand and stored trees fill up with presentation values.
- **A carousel library** such as Swiper, Splide or Embla. More features, but heavier, and its markup would become our template contract.

## Consequences

- The `cms-` class names and the custom properties are a contract that agencies style against. Renaming them is a breaking change.
- The stylesheet and script are included through the shop layout's stylesheet and javascript template events by default. A config switch turns that off for agencies that bundle their own.
- The product card depends on a Sylius shop partial, so the 2.x port swaps it for 2.x's product card.
- Agencies who want a different look restyle the classes, add block styles, or override templates. Adding block styles needs no new block type.
- Shop pages have their own containers, so a block set to full width only reaches the edges where its surroundings allow, such as the default Page layout, which renders its tree edge to edge.
