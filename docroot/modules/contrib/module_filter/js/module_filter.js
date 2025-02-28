/**
 * @file
 */

(($, Drupal) => {
  Drupal.ModuleFilter = Drupal.ModuleFilter || {};

  Drupal.ModuleFilter.localStorage = {
    getItem(key) {
      if (typeof Storage !== 'undefined') {
        return localStorage.getItem(`moduleFilter.${key}`);
      }

      return null;
    },
    getBoolean(key) {
      const item = Drupal.ModuleFilter.localStorage.getItem(key);

      if (item != null) {
        return item === 'true';
      }

      return null;
    },
    setItem(key, data) {
      if (typeof Storage !== 'undefined') {
        localStorage.setItem(`moduleFilter.${key}`, data);
      }
    },
    removeItem(key) {
      if (typeof Storage !== 'undefined') {
        localStorage.removeItem(`moduleFilter.${key}`);
      }
    },
  };

  /**
   * Filter enhancements.
   */
  Drupal.behaviors.moduleFilter = {
    attach() {},
  };
})(jQuery, Drupal);
