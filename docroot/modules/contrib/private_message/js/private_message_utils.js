/**
 * @file
 * Shared utility functions for Private Message module.
 */

((Drupal) => {
  Drupal.PrivateMessageUtils = {
    /**
     * Creates a temporary DOM container.
     *
     * @param {string} html
     *   The HTML string to parse.
     * @return {NodeListOf<ChildNode>}
     *   Parsed HTML content as NodeList.
     */
    parseHTML(html) {
      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = html;

      return document.createDocumentFragment().appendChild(tempDiv).childNodes;
    },
  };
})(Drupal);
