/**
 * @file
 * Defines JavaScript behaviors for the diff module.
 */

(function (Drupal, drupalSettings, once) {
  Drupal.behaviors.diffRevisions = {
    attach() {
      const rows = once('diff-revisions', 'table.diff-revisions tbody tr');
      if (rows.length === 0) {
        return;
      }

      function updateDiffRadios() {
        let newTd = false;
        let oldTd = false;

        rows.forEach((row) => {
          const oldRadio = row.querySelector(
            'input[type="radio"][name="radios_left"]',
          );
          const newRadio = row.querySelector(
            'input[type="radio"][name="radios_right"]',
          );

          if (!oldRadio || !newRadio) {
            return;
          }

          oldRadio.classList.remove('js-hide');
          newRadio.classList.remove('js-hide');

          if (oldRadio.checked) {
            oldTd = true;
            newRadio.classList.add('js-hide');
          } else if (newRadio.checked) {
            newTd = true;
            oldRadio.classList.add('js-hide');
          } else if (drupalSettings.diffRevisionRadios === 'linear') {
            if (newTd && oldTd) {
              newRadio.classList.add('js-hide');
            } else if (!newTd) {
              oldRadio.classList.add('js-hide');
            }
          }
        });
      }

      if (drupalSettings.diffRevisionRadios) {
        rows.forEach((row) => {
          row
            .querySelectorAll(
              'input[name="radios_left"], input[name="radios_right"]',
            )
            .forEach((radio) => {
              radio.addEventListener('change', updateDiffRadios);
            });
        });
        updateDiffRadios();
      }
    },
  };
})(Drupal, drupalSettings, once);
