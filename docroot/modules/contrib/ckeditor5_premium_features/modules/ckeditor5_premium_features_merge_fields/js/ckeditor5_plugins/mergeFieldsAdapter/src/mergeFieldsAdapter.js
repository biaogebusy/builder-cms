/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

/**
 * @file
 * CKEditor 5 MergeFieldsAdapter plugin.
 *
 * This adapter connects the MergeFields plugin with Drupal's configuration.
 */

class MergeFieldsAdapter {
  /**
   * Plugin name.
   *
   * @returns {string}
   */
  static get pluginName() {
    return 'MergeFieldsAdapter';
  }

  /**
   * Constructor.
   *
   * @param {module:core/editor/editor~Editor} editor
   */
  constructor(editor) {
    this.editor = editor;
    if (typeof this.editor.sourceElement === "undefined") {
      return;
    }

    // Get the MergeFields plugin instance
    const mergeFields = this.editor.plugins.get('MergeFields');

    // Get the configuration from Drupal settings
    const config = editor.config.get('mergeFields') || {};

    // Configure the MergeFields plugin with the settings from Drupal
    if (config.fields) {
      mergeFields.setFields(config.fields);
    }
  }

  init() {
    if (typeof this.editor.sourceElement === "undefined") {
      return;
    }
    const config = this.editor.config.get('mergeFields') || {};

    // Get the MergeFields plugin instance
    let data = this.editor.config.get('initialData') || '';

    // Process all elements of config.definitions if available
    if (config.definitions && Array.isArray(config.definitions)) {

      // Iterate through each group
      config.definitions.forEach(group => {
        // Check if this is a group with its own definitions
        if (group.definitions && Array.isArray(group.definitions)) {
          // Process each definition within the group
          group.definitions.forEach(definition => {
            const mergeField = config.prefix + definition.id + config.suffix;
            data = data.replaceAll(definition.token, mergeField);
          });
        }
      });

    }
    this.editor.config.set('initialData', data);
  }

}

export default MergeFieldsAdapter;
