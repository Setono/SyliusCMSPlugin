Creating your first page is a great start to getting to know the CMS capabilities. It will guide you through the process
of creating templates, views, and blocks.

When a page is requested, the CMS will

1. See if that page exists and is eligible to be shown
2. Then render the view associated with the page
3. The view in turn renders the blocks and the blocks are rendered in places determined by the template

From this short description, you can deduce that the basis for our page is the template, so let's create a template.
Click on `Templates` in the menu click `Create`. Input `first_page` in the `Code` field and `{% block sscms_section_content %}{% endblock %}`
in the `Source` field. The internal description field is used for your internal usage to better identify templates later on.
Here is a screenshot of what you should have now:

![First page template](images/first_page_template.png)

{% hint style="info" %}
The prefix `sscms_section_` is how you denote sections inside templates. Hence we have just created a section inside
our template named `content`.
{% endhint %}
