/**
 * @file ai_tools_library.js
 */
(($, Drupal, once) => {

  Drupal.behaviors.aiToolsLibraryRemoveTool = {
    attach: function (context, settings) {
      once('ai-tools-library-remove-tool', '.ai-tools-library-item__remove', context).forEach(function (element) {
        element.addEventListener('click', function (e) {
          e.preventDefault();
          const widgetId = e.target.dataset.widgetId;
          const toolId = e.target.dataset.toolId;
          let tools = context.querySelector('[data-ai-tools-library-form-element-value="' + widgetId + '"]');
          if (tools) {
            let tool_ids = tools.value.split(',');
            tool_ids = tool_ids.filter(function (tool) {
              return tool !== toolId;
            });
            tool_ids = tool_ids.join(',');
            tools.value = tool_ids;
            context.querySelector('[data-ai-tools-library-form-element-update="' + widgetId + '"]').dispatchEvent(new Event('mousedown'));
          }
        });
      })
    }
  }

  /**
   * Updates the selected tools with the provided data.
   *
   * @param {string} data
   *  The data to append to the selection.
   * @param {string} element
   *  The element which contains the tools ids.
   */

  $.fn.setToolsFieldValue = function (data, element) {
    var currentValue = $(element).val();
    $(element).val("".concat(currentValue).concat(currentValue === "" ? "" : ",").concat(data));
  };

})(jQuery, window.Drupal, once);
