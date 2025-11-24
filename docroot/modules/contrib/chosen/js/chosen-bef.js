/**
 * @file
 * Add support for Better Exposed Filters integration.
 */
(function (Drupal, once) {
  'use strict';

  function applyChosenBef(select) {
    const chosenContainer = select.nextElementSibling;
    if (chosenContainer && chosenContainer.classList.contains('chosen-container')) {
      const chosenSearchInput = chosenContainer.querySelector('.chosen-search-input');
      if (chosenSearchInput) {
        chosenSearchInput.setAttribute('data-bef-auto-submit-exclude', 'true');
      }
    }
  }

  Drupal.behaviors.chosenBef = {
    attach: function (context, settings) {
      once('chosenBef', 'select', context).forEach(function (select) {
        if (
          select.nextElementSibling &&
          select.nextElementSibling.classList.contains('chosen-container')
        ) {
          applyChosenBef(select);
        } else {
          select.addEventListener('chosen:ready', function () {
            applyChosenBef(select);
          });
        }
      });
    },
  };
})(Drupal, once);
