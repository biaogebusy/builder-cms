/**
 * @file
 * Defines JavaScript behaviors for the diff module.
 */

(function ($, Drupal, drupalSettings, once) {
  Drupal.behaviors.diffRevisions = {
    attach() {
      const rows = once('diff-revisions', 'table.diff-revisions tbody tr');
      const $rows = $(rows);
      if ($rows.length === 0) {
        return;
      }

      function updateDiffRadios() {
        let newTd = false;
        let oldTd = false;
        if (!$rows.length) {
          return true;
        }
        $rows.each(function () {
          const $row = $(this);
          const $inputs = $row.find('input[type="radio"]');
          const $oldRadio = $inputs.filter('[name="radios_left"]').eq(0);
          const $newRadio = $inputs.filter('[name="radios_right"]').eq(0);
          if (!$oldRadio.length || !$newRadio.length) {
            return true;
          }
          if ($oldRadio.prop('checked')) {
            oldTd = true;
            $oldRadio[0].style.display = 'block';
            $newRadio[0].style.display = 'none';
          } else if ($newRadio.prop('checked')) {
            newTd = true;
            $oldRadio[0].style.display = 'none';
            $newRadio[0].style.display = 'block';
          } else if (drupalSettings.diffRevisionRadios === 'linear') {
            if (newTd && oldTd) {
              $oldRadio[0].style.display = 'block';
              $newRadio[0].style.display = 'none';
            } else if (newTd) {
              $oldRadio[0].style.display = 'block';
              $newRadio[0].style.display = 'block';
            } else {
              $newRadio[0].style.display = 'block';
              $oldRadio[0].style.display = 'none';
            }
          } else {
            $oldRadio[0].style.display = 'block';
            $newRadio[0].style.display = 'block';
          }
        });
        return true;
      }

      if (drupalSettings.diffRevisionRadios) {
        $rows
          .find('input[name="radios_left"], input[name="radios_right"]')
          .click(updateDiffRadios);
        updateDiffRadios();
      }
    },
  };
})(jQuery, Drupal, drupalSettings, once);
