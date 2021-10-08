$().ready(() => {
    'use strict';

    $.fn.extend({
        refreshTemplateSections: function() {
            const $input = $(this);

            $input.on('change', function() {
                const $form = $(this).parentsUntil('form').parent();
                const $sectionsContainer = $($(this).data('sections-container'));
                const loadSectionsUrl = $(this).data('load-sections-url');
                const loadSectionsMethod = $(this).data('load-sections-method');

                $.ajax({
                    method: loadSectionsMethod,
                    url: loadSectionsUrl,
                    data: $form.serialize(),
                    success: function (response) {
                        $sectionsContainer.html(response);

                        $('[data-form-type="collection"]').CollectionForm();
                    },
                });
            });
        }
    });
    $('.js-setono-sylius-cms-template').refreshTemplateSections();
});
