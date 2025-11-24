/**
 * Views data export auto download.
 *
 * Automatically downloads file if downloadEnabled is true.
 */
(function (Drupal, once) {
  Drupal.behaviors.views_data_export_auto_download = {
    attach(context) {
      once(
        'vde-automatic-download',
        '#vde-automatic-download',
        context,
      ).forEach(function (link) {
        link.focus();
        if (link.dataset.downloadEnabled === 'true') {
          window.location.href = link.href;
        }
      });
    },
  };
})(Drupal, once);
