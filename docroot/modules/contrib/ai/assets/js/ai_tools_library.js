/**
 * @file ai_tools_library.js
 */
(($, Drupal, window, { tabbable }, debounce) => {
  /**
   * Wrapper object for the current state of the AI tools library.
   */
  Drupal.AiToolsLibrary = {
    /**
     * When a user interacts with the ai tools library we want the selection to
     * persist as long as the ai tools library modal is opened. We temporarily
     * store the selected items while the user filters the ai tools library view
     * or navigates to different tabs.
     */
    currentSelection: [],
  };

  /**
   * Command to update the current ai tools library selection.
   *
   * @param {Drupal.Ajax} [ajax]
   *   The Drupal Ajax object.
   * @param {object} response
   *   Object holding the server response.
   * @param {number} [status]
   *   The HTTP status code.
   */
  Drupal.AjaxCommands.prototype.updateAiToolsLibrarySelection = function (
    ajax,
    response,
    status,
  ) {
    Object.values(response.aiTools).forEach((value) => {
      Drupal.AiToolsLibrary.currentSelection.push(value);
    });
  };

  /**
   * Load ai tools library content through AJAX.
   *
   * Standard AJAX links (using the 'use-ajax' class) replace the entire library
   * dialog. When navigating to a tools group through the vertical tabs, we only
   * want to load the changed library content. This is not only more efficient,
   * but also provides a more accessible user experience for screen readers.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches behavior to vertical tabs in the ai tools library.
   *
   * @todo Remove when the AJAX system adds support for replacing a specific
   *   selector via a link.
   *   https://www.drupal.org/project/drupal/issues/3026636
   */
  Drupal.behaviors.AiToolsLibraryTabs = {
    attach(context) {
      const $menu = $('.js-ai-tools-library-menu');
      $(once('ai-tools-library-menu-item', $menu.find('a')))
        .on('keypress', (e) => {
          // The AJAX link has the button role, so we need to make sure the link
          // is also triggered when pressing the space bar.
          if (e.which === 32) {
            e.preventDefault();
            e.stopPropagation();
            $(e.currentTarget).trigger('click');
          }
        })
        .on('click', (e) => {
          e.preventDefault();
          e.stopPropagation();

          // Replace the library content.
          const ajaxObject = Drupal.ajax({
            wrapper: 'ai-tools-library-content',
            url: e.currentTarget.href,
            dialogType: 'ajax',
            progress: {
              type: 'fullscreen',
              message: Drupal.t('Processing...'),
            },
          });

          // Override the AJAX success callback to shift focus to the ai tools
          // library content.
          ajaxObject.success = function (response, status) {
            return Promise.resolve(
              Drupal.Ajax.prototype.success.call(ajaxObject, response, status),
            ).then(() => {
              // Set focus to the first tabbable element in the ai tools library
              // content.
              const aiToolsLibraryContent = document.getElementById(
                'ai-tools-library-content',
              );
              if (aiToolsLibraryContent) {
                const tabbableContent = tabbable(aiToolsLibraryContent);
                if (tabbableContent.length) {
                  tabbableContent[0].focus();
                }
              }
            });
          };
          ajaxObject.execute();

          // Set the selected tab.
          $menu.find('.active-tab').remove();
          $menu.find('a').removeClass('active');
          $(e.currentTarget)
            .addClass('active')
            .html(
              Drupal.t(
                '<span class="visually-hidden">Show </span>@title<span class="visually-hidden"> tool</span><span class="active-tab visually-hidden"> (selected)</span>',
                { '@title': $(e.currentTarget).data('title') },
              ),
            );

          // Announce the updated content.
          Drupal.announce(
            Drupal.t('Showing @title tool.', {
              '@title': $(e.currentTarget).data('title'),
            }),
          );
        });
    },
  };

  /**
   * Update the ai tools library selection when loaded or tools are selected.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches behavior to select tools.
   */
  Drupal.behaviors.AiToolsLibraryItemSelection = {
    attach(context, settings) {
      const $form = $(
        '.ai-tools-library-view__rows',
        context,
      );
      const currentSelection = Drupal.AiToolsLibrary.currentSelection;
      const aiToolsLibraryModalSelection = document.querySelector(
        '#ai-tools-library-modal-selection',
      );

      if (aiToolsLibraryModalSelection) {
        // Set the selection in the hidden form element.
        aiToolsLibraryModalSelection.value = currentSelection.join();
        $(aiToolsLibraryModalSelection).trigger('change');
      }
      if (!$form.length) {
        return;
      }

      const $aiTools = $(
        '.ai-tools-library-item input[type="checkbox"]',
        $form,
      );

      // Update the selection array and the hidden form field when
      // an ai tools item is selected.
      $(once('ai-tools-item-change', $aiTools)).on('change', (e) => {
        const id = e.currentTarget.value;

        // Update the selection.
        if (e.currentTarget.checked) {
          // Check if the ID is not already in the selection and add if needed.
          if (!currentSelection.includes(id)) {
            currentSelection.push(id);
          }
        } else if (currentSelection.includes(id)) {
          // Remove the ID when it is in the current selection.
          currentSelection.splice(currentSelection.indexOf(id), 1);
        }

        const aiToolsLibraryModalSelection = document.querySelector(
          '#ai-tools-library-modal-selection',
        );

        if (aiToolsLibraryModalSelection) {
          // Set the selection in the hidden form element.
          aiToolsLibraryModalSelection.value = currentSelection.join();
          $(aiToolsLibraryModalSelection).trigger('change');
        }
      });

      // Apply the current selection to the ai tools library view. Changing the
      // checkbox values triggers the change event for the ai tools items. The
      // change event handles updating the hidden selection field for the form.
      currentSelection.forEach((value) => {
        $form
          .find(`input[type="checkbox"][value="${value}"]`)
          .prop('checked', true)
          .trigger('change');
      });
    },
  };

  /**
   * Clear the current selection.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches behavior to clear the selection when the library modal closes.
   */
  Drupal.behaviors.AiToolsLibraryModalClearSelection = {
    attach() {
      if (!once('ai-tools-library-clear-selection', 'html').length) {
        return;
      }
      window.addEventListener('dialog:afterclose', () => {
        // This empty the array while keeping the existing array reference,
        // to keep event listeners working.
        Drupal.AiToolsLibrary.currentSelection.length = 0;
      });
    },
  };

  Drupal.behaviors.AiToolsLibraryFilter = {
    attach(context, settings) {
      const [input] = once('tools-filter-text', 'input.tools-filter');
      if (!input) {
        return;
      }
      const $toolsWrapper = $('.ai-tools-selection');
      const $tools = $toolsWrapper.find('.ai-tools-library-item');
      function preventEnterKey(event) {
        if (event.which === 13) {
          event.preventDefault();
          event.stopPropagation();
        }
      }
      function filterToolsList(e) {
        const query = e.target.value;
        if (query.length === 0) {
          $tools.show();
        }
        // Case insensitive expression to find query at the beginning of a word.
        const re = new RegExp(`\\b${query}`, 'i');

        function showTool(index, row) {
          const sources = row.querySelectorAll('.option');
          if (sources.length > 0) {
            const textMatch = sources[0].textContent.search(re) !== -1;
            $(row).toggle(textMatch);
          }
        }
        // Search over all rows.
        $tools.show();

        // Filter if the length of the query is at least 2 characters.
        if (query.length >= 2) {
          $tools.each(showTool);

          const visibleTools = $toolsWrapper.find('.ai-tools-library-item:visible');

          Drupal.announce(
            Drupal.formatPlural(
              visibleTools.length,
              '1 tool is available in the modified list.',
              '@count tools are available in the modified list.',
            ),
          );
        }
      }

      $(input).on({
        keyup: debounce(filterToolsList, 200),
        click: debounce(filterToolsList, 200),
        keydown: preventEnterKey,
      });
    }
  }

})(jQuery, Drupal, window, window.tabbable, Drupal.debounce);
