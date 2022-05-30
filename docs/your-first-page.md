Creating your first page is a great start to getting to know the CMS capabilities. It will guide you through the process
of creating templates, views, and blocks.

When a page is requested, the CMS will go through this _flow_:

1. See if that page exists and is eligible to be shown
2. Then render the view associated with the page
3. The view in turn renders the blocks and the blocks are rendered in places determined by the template

## Creating your first template

From this short description, you can deduce that the basis for our page is the template, so let's create a template.
Click on `Templates` in the menu and click `Create`. Input `first_page` in the `Code` field and 
`\{% block sscms_section_content %\}\{% endblock %\}` in the `Source` field.
The internal description field is used for your internal usage to better identify templates later on.
Here is a screenshot of what you should have now:

![First template](images/first_template.png)

{% hint style="info" %}
The prefix `sscms_section_` is how you denote sections inside templates. Hence we have just created a section inside
our template named `content`.
{% endhint %}

Last, but not least, hit `Create`.

## Creating your first block

Next up is creating the blocks we want to appear in the `content` section of the template. Go to `Blocks` and click `Create`.
Input `first_block` in the `Code` field and add your content to the content fields. You should end up with something like this:

![First block](images/first_block.png)

Hit `Create`.

## Creating your first view

Now we have to tie the blocks to the section we defined in the template. We do this in a view. Click on `Views` and
then `Create`.
