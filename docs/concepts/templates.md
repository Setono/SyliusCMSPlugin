Templates are your starting point when you want to create a landing page, a layout of your taxon description, or a section
with multiple rows or columns in your product page.

Technically the templates are Twig templates and are loaded as Twig templates. This comes with a lot of built-in 
advantages:

- You have the full power of Twig available
- You can reference a template created in the CMS directly in your Sylius application
- Cache is already handled

## The power of Twig

Twig is the template language used in Symfony applications (hence also in Sylius). It's very powerful in and of itself
as you can read by going through the extensive [documentation](https://twig.symfony.com/).

This also implies that you can use all the tags, filters, functions etc. from Twig. Let's say you wanted to create a
base template for landing pages and use that template in other child templates; just use the `extends` tag. There are
lots of built-in [features](https://twig.symfony.com/doc/2.x/) to choose from, but if you want something specific to
your use case, just ask your developer to create a Twig extension, and it will be available for you to use in your
templates.

## Reference templates directly in your Sylius application

Say you wanted to control the layout of your product page. Just create a template named `product` and reference this
template in your Sylius application instead of the built-in templates. Now you are in complete control of everything
that happens on your product page.

## Cache is handled

By using Twig we gain a few advantages with regard to speed: Cache and compilation. First, all Twig templates
are compiled into simple PHP files. Secondly, these PHP files are saved on the filesystem (the cache).

So when you create a template, and it's referenced, what happens is that it gets compiled and saved to the filesystem.
This implies that you should not worry about the speed of having templates saved in the database in contrast to saving
them on the filesystem. There is literally no difference - both are _fast_!
