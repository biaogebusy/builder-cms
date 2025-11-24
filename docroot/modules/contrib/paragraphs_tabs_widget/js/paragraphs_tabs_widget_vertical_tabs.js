(function ($, once) {
  /**
   * Update tab summaries when the title field changes.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.paragraphs_tabs_widgetVerticalTabs = {
    attach: function attach(context, settings) {
      // If there are vertical tab settings present in the document fragment
      // currently being attached to the page...
      if (settings.hasOwnProperty('paragraphs_tabs_widget_vertical_tabs')) {
        // Find each details element that represents a tab, and add a callback
        // to set its summary.
        $(
          'details[data-paragraph-tabs-widget-tab-group]',
          context,
        ).drupalSetSummary(function (summaryContext) {
          let currentTabSummarySelector;
          const tabsGroup = $(summaryContext).attr(
            'data-paragraph-tabs-widget-tab-group',
          );
          let summary = '';
          let rawSummary;
          // If the tab group is not empty, and we can find a matching tab
          // group in the paragraphs_tabs_widget_vertical_tabs settings, and
          // that tab group has a summary selector, then set this tab's
          // summary to the value of the first element chosen by that selector
          // (in the context of this tab).
          if (
            tabsGroup &&
            settings.paragraphs_tabs_widget_vertical_tabs.hasOwnProperty(
              tabsGroup,
            ) &&
            settings.paragraphs_tabs_widget_vertical_tabs[
              tabsGroup
            ].hasOwnProperty('summarySelector') &&
            settings.paragraphs_tabs_widget_vertical_tabs[tabsGroup]
              .summarySelector
          ) {
            currentTabSummarySelector =
              settings.paragraphs_tabs_widget_vertical_tabs[tabsGroup]
                .summarySelector;
            rawSummary =
              $(currentTabSummarySelector, summaryContext)[0].value || '';
            summary = Drupal.checkPlain(rawSummary);
          }

          return summary;
        });
      }

      // If there is a vertical tabs widget present in the document fragment
      // currently being attached to the page, then move the "add more" button
      // to the bottom of its tab menu.
      once(
        'paragraphs_tabs_widget-move-addmore-button',
        '[data-paragraphs-tabs-widget-addmore-group]',
        context,
      ).forEach(function (element) {
        const $addmoreButton = $(element);
        const $menu = $addmoreButton
          .closest('.vertical-tabs')
          .find('.vertical-tabs__menu');
        $addmoreButton.appendTo($menu);
      });
    },
  };
})(jQuery, once);
