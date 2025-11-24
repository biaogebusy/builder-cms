/**
 * @file
 * Defines the ajax command for updating product urls on variation selection.
 */

((Drupal) => {
  /**
   * Command to update the current url on variation selection.
   *
   * @param {Drupal.Ajax} ajax
   *   {@link Drupal.Ajax} object created by {@link Drupal.ajax}.
   * @param {object} response
   *   The response from the Ajax request.
   * @param {string} [response.variation_id]
   *   The variation ID that should be updated in the url.
   */
  Drupal.AjaxCommands.prototype.updateProductUrl = (ajax, response) => {
    const params = new URLSearchParams(window.location.search);
    params.set('v', response.variation_id);
    window.history.replaceState(
      {},
      document.title,
      `${window.location.pathname}?${params.toString()}`,
    );
  };
})(Drupal);
