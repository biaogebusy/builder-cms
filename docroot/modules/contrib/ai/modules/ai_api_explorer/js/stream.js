(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.chatFormStream = {
    attach: (context) => {
      let streamElements = $('[data-response]', context);
      once('data-streamed', streamElements).forEach((item) => {
        const element = $(item);
        const form = element.closest('form');
        // Use plain javascript with capture, so we can run first.
        item.addEventListener('mousedown', (event) => {
          // Get the loading message within the form context.
          const loadingMessage = form.find('#ai-loading-message-chat');

          // Show the loader only if streaming is checked.
          if (form.find('#edit-streamed').prop('checked') && loadingMessage.length) {
            loadingMessage.show();
          }

          // If streaming is not checked return.
          if (!form.find('#edit-streamed').prop('checked')) {
            return;
          }
          // Check all click events on the button.
          event.preventDefault();
          // Stop the default event.
          event.stopImmediatePropagation();
          event.stopPropagation();
          const clickedElement = $(event.currentTarget);
          const responseField = $('#' + clickedElement.attr('data-response'));
          let data = form.serializeArray();

          // Push an event for the current submission.
          data.push({
            name: event.currentTarget.name,
            value: event.currentTarget.value
          });

          let first = true;

          $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: data,
            xhrFields: {
              onprogress: function(event) {
                try {
                  // Check if this is the first progress event
                  if (first) {
                    first = false;
                    // Scroll to the top of the window just once.
                    window.scrollTo(0, 0);
                  }
                  const response = event.currentTarget.response;

                  // Check if response is valid
                  if (!response) {
                    return;
                  }

                  // Try to parse as JSON first to check if it's an error response
                  try {
                    const jsonResponse = JSON.parse(response);
                    if (jsonResponse.error) {
                      responseField.html(`<div class="messages messages--error">${Drupal.t('Error: @message', {'@message': jsonResponse.error})}</div>`);
                      return;
                    }
                  } catch (e) {
                    // Not JSON, proceed with text response
                  }

                  // Validate response is text content
                  if (typeof response === 'string') {
                    // Check if response contains full HTML document
                    if (response.includes('<!DOCTYPE html>') || response.includes('<html')) {
                      // Error - received full page instead of stream
                      responseField.html(`<div class="messages messages--error">${Drupal.t('Invalid response received. Please check your configuration.')}</div>`);
                      return;
                    }

                    // Valid streaming response
                    responseField.html(response.replaceAll("\n", "<br />"));
                  }
                } catch (error) {
                  console.error('Error processing stream response:', error);
                  responseField.html(`<div class="messages messages--error">${Drupal.t('Error processing response')}</div>`);
                }
              }
            },
            complete: function() {
              // Hide the loading message when the streamed response completes
              if (loadingMessage.length) {
                loadingMessage.hide();
              }
            },
            error: function(xhr, status, error) {
              // Handle AJAX errors
              responseField.html(`<div class="messages messages--error">${Drupal.t('Error: @message', {'@message': error})}</div>`);
              if (loadingMessage.length) {
                loadingMessage.hide();
              }
            }
          });
        }, true);
      });

    // Hide the loading message for non-streamed AJAX responses.
    $(document).ajaxComplete(function (event, xhr, settings) {
      if (
        settings.extraData &&
        settings.extraData._triggering_element_name === 'op'
      ) {
        $('#ai-loading-message-chat').hide();
      }
    });
  },
};
})(jQuery, Drupal, drupalSettings);
