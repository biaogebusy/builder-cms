/**
 * @file
 * Attaches entity-type selection behaviors to the widget form.
 */

((Drupal, drupalSettings) => {
  Drupal.behaviors.dynamicEntityReferenceWidget = {
    attach(context) {
      function dynamicEntityReferenceWidgetSelect(e) {
        const selectElement = e.currentTarget;
        const container = selectElement.closest('.container-inline');
        const autocomplete = container.querySelector('.form-autocomplete');
        autocomplete.value = '';
        const entityTypeId = selectElement.value;
        autocomplete.dataset.autocompletePath =
          drupalSettings.dynamic_entity_reference[
            selectElement.dataset.dynamicEntityReference
          ][entityTypeId];
        Drupal.autocomplete.cache[autocomplete.id] = {};
      }
      Object.keys(drupalSettings.dynamic_entity_reference || {}).forEach(
        (fieldClass) => {
          const field = context.querySelector(`.${fieldClass}`);
          if (field && !field.classList.contains(`${fieldClass}-processed`)) {
            field.classList.add(`${fieldClass}-processed`);
            field.addEventListener(
              'change',
              dynamicEntityReferenceWidgetSelect,
            );
          }
        },
      );
    },
  };
})(Drupal, drupalSettings);
