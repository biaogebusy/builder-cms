(function ($) {
  $(window).off('drupalContextualLinkAdded');

  // Remove all contextual links outside the layout.
  $(document).on('drupalContextualLinkAdded', (event, data) => {
    const element = data.$el;
    const contextualId = element.attr('data-contextual-id');
    if (contextualId && !contextualId.startsWith('layout_builder_')) {
      element.remove();
    }
  });
})(jQuery);
