(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.ai_content_suggestions_toggle = {
    attach: function (context) {
      // Hide all select containers initially
      context.querySelectorAll('.toggle_content_suggestion_select').forEach(container => {
        container.style.display = 'none';
      });

      // Handle toggle links
      once('ai-content-suggestions-toggle', '.toggle_content_suggestion_fields', context).forEach(element => {
        element.addEventListener('click', function(e) {
          e.preventDefault();
          const formItem = this.closest('.form-item');
          const selectContainer = formItem.querySelector('.toggle_content_suggestion_select');

          // Toggle with animation
          if (selectContainer.style.display === 'none') {
            selectContainer.style.display = 'block';
            selectContainer.style.opacity = '0';
            requestAnimationFrame(() => {
              selectContainer.style.transition = 'opacity 100ms ease-in-out';
              selectContainer.style.opacity = '1';
            });
          } else {
            selectContainer.style.opacity = '0';
            selectContainer.addEventListener('transitionend', function handler() {
              selectContainer.style.display = 'none';
              selectContainer.removeEventListener('transitionend', handler);
            });
          }
        });
      });
    }
  };

})(Drupal, once);
