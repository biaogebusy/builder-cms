/**
 * Views data export auto download.
 *
 * Automatically downloads file if downloadEnabled is true.
 */
(function (Drupal, once) {
  Drupal.behaviors.views_data_export_auto_download = {
    attach: function () {
      once('vde-automatic-download', '#vde-automatic-download').forEach(function (link) {
        link.focus();
        if (link.dataset.downloadEnabled === 'true') {
          location.href = link.href;
        }
      })
    }
  };
})(Drupal, once);
