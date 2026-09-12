/**
 * @file
 * Behaviors for the autocomplete search widget in the drupal core toolbar.
 *
 * @param {Function} once
 *   The once library used to ensure behaviors are attached only once.
 * @param {Drupal} Drupal
 *   The Drupal global used to define behaviors.
 * @param {jQuery} $
 *   The jQuery library used to instantiate the autocomplete widget.
 */

((once, Drupal, $) => {
  /**
   * Integrates jQuery UI Autocomplete with Admin Toolbar for searching links.
   *
   * @namespace Drupal.behaviors.adminToolbarSearch
   *
   * @type {Object}
   *   More specifically, a Drupal behavior.
   *
   * @prop {Function} attach
   *   Attaches the behavior to instantiate the jQuery autocomplete search field
   *   in the admin toolbar.
   *
   * @see https://api.jqueryui.com/autocomplete/
   */
  Drupal.behaviors.adminToolbarSearch = {
    attach(context) {
      // Attach only when the whole document is loaded.
      if (context !== document) {
        return;
      }
      // Search input field ID in toolbar tray and mobile support version.
      const searchInputID = '#admin-toolbar-search-input';
      // Search input field ID in toolbar bar.
      const searchFieldInputID = '#admin-toolbar-search-field-input';

      // Ensure the autocomplete is only called once when the toolbar is loaded.
      once('admin-toolbar-search', '#toolbar-bar', context).forEach(
        (toolbarBar) => {
          // Get the two search input fields on which to call the autocomplete.
          const searchInputFields = toolbarBar.querySelectorAll(
            [searchInputID, searchFieldInputID].join(', '),
          );

          // Skip any further processing if no search input field is found.
          if (searchInputFields.length === 0) {
            return;
          }

          /**
           * The list of menu links collected from the admin toolbar trays for
           * autocomplete searching.
           *
           * @param {Array}
           */
          const menuLinks = [];

          /**
           * Helper function to get the display label for a menu link item.
           *
           * This function constructs a breadcrumb-like label for the given menu
           * link item by traversing its parent menu items, for example:
           * Configuration > Development > Performance > Performance
           *
           * @param {HTMLLinkElement} item
           *  The link element item to be processed.
           *
           * @return {String}
           *   The HTML for the list item to be displayed in the autocomplete
           *   suggestions.
           */
          const getItemDisplayLabel = (item) => {
            // Start with an empty array of breadcrumbs.
            const breadcrumbs = [];
            let parent = item.closest('.menu-item');
            // Loop through all the parents with the class 'menu-item'.
            while (parent) {
              const link = parent.querySelector(
                // For each parent menu item, find the first child link without
                // the CSS class 'admin-toolbar-search-ignore', to give the
                // ability to ignore certain menu items from the breadcrumb.
                'a:first-child:not(.admin-toolbar-search-ignore)',
              );
              if (link) {
                // Extract the text of the links of the menu items.
                breadcrumbs.unshift(link.innerText);
              }
              // Move to the next parent menu item.
              parent = parent.parentNode.closest('.menu-item');
            }
            // Return the breadcrumbs joined with ' > ' separator.
            return breadcrumbs.join(' > ');
          };

          /**
           * Function callback for handling the autocomplete search.
           *
           * This function filters through the list of menu links collected from
           * the admin toolbar trays and returns the ones matching the search
           * query:
           * The search matches first the exact matches of the whole search
           * query, then adds suggestions matching all searched keywords (AND).
           *
           * Query strings are stripped from link URLs for searching to prevent
           * generated tokens or destinations from appearing in the results.
           *
           * @param {String} term
           * The search term entered by the user.
           *
           * @return {Array}
           *   The list of matched menu links suggestions.
           */
          const handleAutocomplete = (term) => {
            // Split the search query keywords separated by spaces (' ') into a
            // list.
            const keywords = term.split(' ');
            const suggestions = [];

            menuLinks.forEach((element) => {
              // Strip query strings from link URLs for searching to prevent generated
              // tokens or destinations from appearing in the search results.
              const linkUrl = element.linkUrl.split('?')[0].toLowerCase();
              // Concatenate the label and link URL for searching.
              const label = `${element.label.toLowerCase()} ${linkUrl}`;

              // Try selecting first the exact matches of the whole search query.
              if (label.indexOf(term.toLowerCase()) >= 0) {
                suggestions.push(element);
              } else {
                // Add suggestions matching *all* searched keywords.
                let matchCount = 0;
                keywords.forEach((keyword) => {
                  if (label.indexOf(keyword.toLowerCase()) >= 0) {
                    matchCount += 1;
                  }
                });
                // Keep the suggestion only if *all* keywords matched (AND).
                if (matchCount === keywords.length) {
                  suggestions.push(element);
                }
              }
            });
            // Return the list of matched menu links suggestions.
            return suggestions;
          };

          /**
           * Initialize the autocomplete widget for each search input field.
           *
           * The autocomplete widgets use the same menu links array for search
           * suggestions.
           *
           * @see https://api.jqueryui.com/autocomplete/
           */
          searchInputFields.forEach((searchInputField) => {
            // Initialize the jQuery UI Autocomplete widget.
            $(searchInputField)
              .autocomplete({
                // Minimum characters to trigger the autocomplete.
                minLength: 2,
                // Position the autocomplete list below the input field.
                position: { collision: 'fit' },
                // Source callback to provide the autocomplete suggestions.
                source(request, response) {
                  // Call the handleAutocomplete function to get the results.
                  response(handleAutocomplete(request.term));
                },
                // Handle the selection of an autocomplete item.
                select(event, ui) {
                  if (ui.item.value) {
                    // Navigate to the selected link URL.
                    window.location.href = ui.item.linkUrl;
                  }
                  return false;
                },
              })
              // Override the default rendering of the autocomplete list items.
              .data('ui-autocomplete')._renderItem = (ul, item) => {
              // Add a custom CSS class to the autocomplete list.
              ul.addClass('admin-toolbar-search-autocomplete-list');
              // Return the formatted list item HTML with link label and URL.
              return $('<li>')
                .append(
                  `<div><a href="${item.linkUrl}">${item.label}</a></div>`,
                )
                .appendTo(ul);
            };

            /**
             * Initialize the menu links for autocomplete searches.
             *
             * Populates the links in admin toolbar search only when the input
             * fields are focused:
             * - Collect all the links available in the admin toolbar trays
             *   ('.toolbar-tray') with the drupal custom data attribute:
             *   - 'data-drupal-link-system-path'
             * - Optionally, fetch extra links through an AJAX call to the
             *   server with a specific controller.
             */
            searchInputField.addEventListener('focus', () => {
              // Populate only when links array is empty (only the first time).
              if (menuLinks.length === 0) {
                // Collect all the links available in the admin toolbar trays
                // ('.toolbar-tray') with the drupal custom data attribute:
                // - 'data-drupal-link-system-path'.
                document
                  .querySelectorAll(
                    `.toolbar-tray a[data-drupal-link-system-path]`,
                  )
                  .forEach((element) => {
                    // Save each link in the menuLinks array for filtering with
                    // autocomplete.
                    menuLinks.push({
                      // The link text is used as value to be displayed in the
                      // input field when selected with keyboard.
                      value: element.innerText,
                      // The label is used to display the full path of the link
                      // in the autocomplete suggestions.
                      label: Drupal.checkPlain(getItemDisplayLabel(element)),
                      // The link URL is used for navigation when the item is
                      // selected.
                      linkUrl: element.href,
                    });
                  });

                // When the admin toolbar tools module is enabled, support
                // loading extra links.
                if (drupalSettings.adminToolbarSearch.loadExtraLinks) {
                  // Optionally, fetch extra links through an AJAX call to the
                  // server with a specific controller returning a JSON array.
                  fetch(Drupal.url('admin/admin-toolbar-search'))
                    .then((response) => response.json())
                    .then((dataParam) => {
                      // Merge the results of the JSON call into the array of
                      // menu links so it can be searched as well.
                      dataParam.forEach((dataParamItem) => {
                        menuLinks.push({
                          value: dataParamItem.labelRaw,
                          // The full path is already provided by the controller
                          // so a call to 'getItemDisplayLabel' is not needed.
                          label: Drupal.checkPlain(dataParamItem.labelRaw),
                          linkUrl: dataParamItem.value,
                        });
                      });
                    });
                }
              }
            });
          });

          /**
           * Focus the search input field when the search tab is clicked.
           *
           * Clicking on the search icon in the toolbar bar toggles the
           * visibility of the search tray and focuses the search input field
           * for immediate typing.
           */
          toolbarBar
            // Attach the click event to the search tab 'span' element, since
            // the tab has no link.
            .querySelector('#admin-toolbar-search-tab .toolbar-item')
            .addEventListener('click', (e) => {
              e.preventDefault();
              // Toggle the search tray visibility.
              const searchTabTray = e.target.nextElementSibling;
              searchTabTray.classList.toggle('is-active');
              // Focus the search input field.
              searchTabTray.querySelector(searchInputID).focus();
            });
        },
      );
    },
  };
})(once, Drupal, jQuery);
