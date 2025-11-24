/**
 * @file
 * Attaches behavior for the Multiselect module.
 */
(($, Drupal, drupalSettings) => {
  /**
   * Provide the summary information for the block settings vertical tabs.
   */
  Drupal.behaviors.multiSelect = {
    attach(context) {
      const $submit = $(
        once('multiselect', '.js-form-type-multiselect', context),
      )
        .parents('form')
        .find('.form-submit');
      const $multiselect = $(
        once('multiselect', 'select.multiselect-available', context),
      );
      const $multiselectAvailable = $(
        once('multiselectAvailable', 'select.multiselect-available', context),
      );
      const $multiselectSelected = $(
        once('multiselectSelected', 'select.multiselect-selected', context),
      );
      const $multiselectAdd = $(
        once('multiselectAdd', 'li.multiselect-add', context),
      );
      const $multiselectRemove = $(
        once('multiselectRemove', 'li.multiselect-remove', context),
      );
      const widths = drupalSettings.multiselect.widths ?? null;

      // Note: Doesn't matter what sort of submit button it is really (preview or submit).
      // Selects all the items in the selected box (so they are actually selected) when submitted.
      $submit.on('click mousedown', () => {
        $submit.selectAll($('select.multiselect-selected'));
      });

      // Do the same (select all options) when clicking Save in quickedit scenario.
      $(once('multiselect', '.action-save.quickedit-button')).click(() => {
        $submit.selectAll($('select.multiselect-selected'));
      });

      // Remove the items that haven't been selected from the select box.
      $multiselect.each(() => {
        const $parent = $(this).parents('.multiselect-wrapper');
        const $available = $('div.multiselect-available select', $parent);
        const $selected = $('div.multiselect-selected select', $parent);
        $available.removeContentsFrom($selected, $available);
      });

      // Moves selection if it's double clicked to selected box.
      // $multiselectAvailable.on('dblclick', function (e) {
      $multiselectAvailable.on('dblclick', (e) => {
        const $parent = $(e.currentTarget).parents('.multiselect-wrapper')[0];
        const $available = $('div.multiselect-available select', $parent);
        const $selected = $('div.multiselect-selected select', $parent);
        $available.moveSelectionTo($selected, $available);
      });

      // Moves selection if it's double clicked to unselected box.
      $multiselectSelected.on('dblclick', (e) => {
        const $parent = $(e.currentTarget).parents('.multiselect-wrapper')[0];
        const $available = $('div.multiselect-available select', $parent);
        const $selected = $('div.multiselect-selected select', $parent);
        $selected.moveSelectionTo($available, $selected);
      });

      // Moves selection if add is clicked to selected box.
      $multiselectAdd.on('click', (e) => {
        e.preventDefault();
        const $parent = $(e.currentTarget).parents('.multiselect-wrapper')[0];
        const $available = $('div.multiselect-available select', $parent);
        const $selected = $('div.multiselect-selected select', $parent);
        $available.moveSelectionTo($selected, $available);
      });

      // Moves selection if remove is clicked to selected box.
      $multiselectRemove.on('click', (e) => {
        e.preventDefault();
        const $parent = $(e.currentTarget).parents('.multiselect-wrapper')[0];
        const $available = $('div.multiselect-available select', $parent);
        const $selected = $('div.multiselect-selected select', $parent);
        $selected.moveSelectionTo($available, $selected);
      });

      if (widths) {
        $(context)
          .find(
            'div.multiselect-available, div.multiselect-selected, select.form-multiselect',
          )
          .width(widths);
      }
    },
  };
})(jQuery, Drupal, drupalSettings);

/**
 * Selects all the items in the select box it is called from.
 * Usage $('nameofselectbox').selectAll();
 */
jQuery.fn.selectAll = (selected) => {
  selected.each((item, list) => {
    for (let x = 0; x < list.length; x++) {
      const option = list[x];
      option.selected = true;
    }
  });
};

/**
 * Removes the content of this select box from the target.
 * Usage $('nameofselectbox').removeContentsFrom(target_selectbox);
 */
jQuery.fn.removeContentsFrom = (...args) => {
  // The callback of values array to add options.
  const dest = args[0];
  // The callback of values array to remove options.
  const send = args[1];
  send.each((items) => {
    for (let x = items.length - 1; x >= 0; x--) {
      dest.removeOption(items[x].value);
    }
  });
};

/**
 + * Moves the selection to the select box specified.
 + * Usage $('nameofselectbox').moveSelectionTo(destination_selectbox);
 + */
jQuery.fn.moveSelectionTo = (...args) => {
  // The callback of values array to add options.
  const dest = args[0];
  // The callback of values array to remove options.
  const send = args[1];
  send.each((item, list) => {
    for (let x = 0; x < list.length; x++) {
      const option = list[x];
      if (option.selected) {
        dest.addOption(option, dest);
        list.remove(x);
        x -= 1;
      }
    }
  });
};

/**
 + * Adds an option to a select box.
 + * Usage $('nameofselectbox').addOption(optiontoadd);
 + */
jQuery.fn.addOption = (...args) => {
  // The option to add.
  const option = args[0];
  // The callback of values array to add options.
  const dest = args[1];
  dest.each((item, list) => {
    // Had to alter code to this to make it work in IE.
    const anOption = document.createElement('option');
    anOption.text = option.text;
    anOption.value = option.value;
    // The select list to add options.
    list[list.length] = anOption;
    return false;
  });
};

/**
 + * Removes an option from a select box.
 + * usage $('nameofselectbox').removeOption(valueOfOptionToRemove);
 + */
jQuery.fn.removeOption = (...args) => {
  const targOption = args[0];
  this.each(() => {
    for (let x = this.options.length - 1; x >= 0; x--) {
      const option = this.options[x];
      if (option.value === targOption) {
        this.remove(x);
      }
    }
  });
};
