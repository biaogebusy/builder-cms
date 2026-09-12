(function tourautoModule($, Drupal) {
  Drupal.behaviors.tourauto = {};
  Drupal.behaviors.tourauto.attach = function tourautoAttach(
    context,
    settings,
  ) {
    once('tourauto', 'body').forEach(function tourautoForEach() {
      if (settings.tourauto_open) {
        // Wait for the tour module to be fully initialized
        const waitForTourModule = function waitForTourModule() {
          if (Drupal.tour && Drupal.tour.setActive) {
            Drupal.tour.setActive(true);
          } else {
            setTimeout(waitForTourModule, 50);
          }
        };

        // Start checking after a short delay to let tour module initialize
        setTimeout(waitForTourModule, 100);
      }
    });
  };
})(jQuery, Drupal);
