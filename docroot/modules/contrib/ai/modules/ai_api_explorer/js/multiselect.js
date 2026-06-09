(function ($, Drupal, drupalSettings) {
  $(document).ready(function () {
    multipleSelect('#edit-function-calls', {
      name: 'name-my-select',
      single: false,
      useSelectOptionLabelToHtml: true,
      width: "100%",
      filter: true,
    });
  });

})(jQuery, Drupal, drupalSettings);
