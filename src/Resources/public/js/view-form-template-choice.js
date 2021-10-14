class CMSViewTemplateChoiceForm {
    constructor(element) {
        this.updateItem = this.updateItem.bind(this);

        this.$element = $(element);
        this.$collectionContainer = $(this.$element.data('collection-container'));
        this.$element.on('change', this.updateItem);
    }

    updateItem(event) {
        event.preventDefault();

        const templateCode = this.$element.val();
        const prototype = $(`.js-setono-sylius-cms-sections-prototype[data-prototype-name="${templateCode}"]`).html();

        this.$collectionContainer.html(prototype);
        $('[data-form-type="collection"]').CollectionForm();
    }
}

$.fn.CMSViewTemplateChoiceForm = function CMSViewTemplateChoiceFormPlugin(option) {
    this.each((idx, el) => {
        const $element = $(el);
        const data = $element.data('CMSViewTemplateChoiceForm');
        const options = typeof option === 'object' && option;

        if (!data) {
            $element.data('CMSViewTemplateChoiceForm', new CMSViewTemplateChoiceForm(el, options));
        }
    });
};

$(function() {
    $.fn.CMSViewTemplateChoiceForm.Constructor = CMSViewTemplateChoiceForm;

    $('.js-setono-sylius-cms-template-choice-input').CMSViewTemplateChoiceForm();
});
