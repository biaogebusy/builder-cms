/**
 * @file
 * The video_embed_field lazy loading videos.
 */

(($, once) => {
  Drupal.behaviors.video_embed_field_lazyLoad = {
    attach(context, settings) {
      $(
        once('video-embed-field-lazy', '.video-embed-field-lazy', context),
      ).click(function onClick(e) {
        // Swap the lightweight image for the heavy JavaScript.
        e.preventDefault();
        const $el = $(this);
        $el.html($el.data('video-embed-field-lazy'));
        Drupal.attachBehaviors($el[0], settings);
      });
    },
  };
})(jQuery, once);
