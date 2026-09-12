/**
 * @file
 * Defines Javascript behaviors for the content lock module.
 */

(function (Drupal, once) {
  /**
   * Behaviors for the content lock settings form.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the content lock settings form behavior.
   */
  Drupal.behaviors.contentLockSettings = {
    attach(context, settings) {
      once(
        'content-lock-settings',
        '.content-lock-entity-settings[value="*"]',
        context,
      ).forEach(function (elem) {
        // Init
        Drupal.behaviors.contentLockSettings.toggleBundles.call(elem);
        // Change
        elem.addEventListener(
          'change',
          Drupal.behaviors.contentLockSettings.toggleBundles,
        );
      });
      once(
        'content-lock-settings',
        '.content-lock-entity-types input',
        context,
      ).forEach(function (elem) {
        elem.addEventListener(
          'change',
          Drupal.behaviors.contentLockSettings.toggleEntityType,
        );
      });
    },

    /**
     * Toggle the bundle rows if all option is changed.
     */
    toggleBundles() {
      const allBundlesSelected = this.checked;
      this.closest('tbody')
        .querySelectorAll('.bundle-settings')
        .forEach((bundleSettings) => {
          // If the "All bundles" checkbox is checked then uncheck and disable
          // all other options.
          const checkboxes =
            bundleSettings.querySelectorAll('[type="checkbox"]');
          if (allBundlesSelected) {
            checkboxes.forEach((checkbox) => {
              checkbox.disabled = true;
              checkbox.checked = false;
              checkbox.classList.add('is-disabled');
            });
            bundleSettings.classList.add('hidden');
          } else {
            checkboxes.forEach((checkbox) => {
              checkbox.disabled = false;
              checkbox.classList.remove('is-disabled');
            });
            bundleSettings.classList.remove('hidden');
          }
        });
    },

    /**
     * Remove all selected bundles or auto select all when changing an entity type.
     */
    toggleEntityType() {
      const entityTypeId = this.value;
      if (this.checked) {
        document
          .querySelectorAll(
            `.${entityTypeId} .content-lock-entity-settings[value="*"]`,
          )
          .forEach((checkbox) => {
            checkbox.checked = true;
            checkbox.dispatchEvent(new Event('change'));
          });
      } else {
        document
          .querySelectorAll(`.${entityTypeId} .content-lock-entity-settings`)
          .forEach((checkbox) => {
            checkbox.checked = false;
          });
      }
    },
  };
})(Drupal, once);
