/**
 * @file
 * Defines Javascript behaviors for the admin comment form.
 */

((Drupal, once) => {
  Drupal.behaviors.commerceLogAdminCommentForm = {
    attach() {
      // Submit the admin order comment form via command + enter.
      once('admin-comment-form-textarea', '.log-comment-form textarea').forEach(
        (textarea) =>
          textarea.addEventListener('keydown', (event) => {
            // Check for Enter key press (keyCode 13 or event.key === 'Enter')
            if (event.key === 'Enter' || event.keyCode === 13) {
              // Check for Control (Windows/Linux) or Command (Mac) key.
              if (event.ctrlKey || event.metaKey) {
                // Prevent the default behavior (e.g., adding a new line in a textarea).
                event.preventDefault();
                // Submit the form.
                textarea.form.submit();
              }
            }
          })
      );
    },
  };
})(Drupal, once);
