/**
 * @file
 * The video_embed_field colorbox integration.
 */

(($, Drupal, once) => {
  Drupal.behaviors.video_embed_field_colorbox = {
    attach(context, settings) {
      $(
        once(
          'video-embed-field-launch-modal',
          '.video-embed-field-launch-modal',
          context,
        ),
      ).click(function onClick(e) {
        // Allow the thumbnail that launches the modal to link to other places
        // such as video URL, so if the modal is sidestepped, things degrade
        // gracefully.
        e.preventDefault();
        $.colorbox(
          $.extend(settings.colorbox, {
            html: $(this).data('video-embed-field-modal'),
          }),
        );
      });
      // Reattach Drupal Behaviors when cbox is ready.
      once('video-embed-field-responsive-modal', 'html').forEach(function () {
        $(this).on('cbox_complete', () => {
          const $modalContent = $(
            '.video-embed-field-responsive-modal',
          ).parent();
          if ($modalContent.length) {
            Drupal.attachBehaviors($modalContent[0], settings);
          }
        });
      });
    },
  };
})(jQuery, Drupal, once);
