/**
 * @file
 * CKEditor 'codesnippet' plugin admin behavior.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  /**
   * Provides the summary for the "codesnippet" plugin settings vertical tab.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches summary behaviour to the "codesnippet" settings vertical tab.
   */
  Drupal.behaviors.ckeditorCodeSnippetSettingsSummary = {
    attach: function () {
      $('[data-ckeditor-plugin-id="codesnippet"]').drupalSetSummary(function (context) {
        var style = 'None selected';
        var languages = '0 languages selected';
        var selected_style = $('#edit-editor-settings-plugins-codesnippet-highlight-style').val();
        var selected_languages = $('#edit-editor-settings-plugins-codesnippet-highlight-languages input:checked').length;

        if (typeof selected_style !== 'undefined') {
          style = selected_style;
        }

        if (typeof selected_languages !== 'undefined') {
          languages = selected_languages + ' languages selected';
        }

        var output = '';
        output += Drupal.t('@style', {'@style': style});
        output += '<br />';
        output += Drupal.t('@languages', {'@languages': languages});
        return output;
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
