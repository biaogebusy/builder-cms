/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

class CollaborationStorage {
  constructor( editor ) {
    this.editor = editor;
    this.elementId = null;
    if (typeof this.editor.sourceElement !== "undefined") {
      this.elementId = this.editor.sourceElement.dataset.ckeditor5PremiumElementId;
    }
  }

  /**
   * Checks if collaboration is set to be disabled and blocks the specified command (button).
   *
   * @param commandName
   *   Command name (related to a button)
   *
   * @returns {boolean}
   *   TRUE if command was blocked, FALSE otherwise.
   */
  processCollaborationCommandDisable(commandName) {
    if (!this.isCollaborationDisabled()) {
      return false;
    }

    const command = this.editor.commands._commands.get( commandName );

    if (typeof command == 'undefined') {
      return true;
    }

    command.forceDisabled( 'premium-features-module' );

    return true;
  }

  /**
   * Checks if collaboration is set to be disabled and blocks the revision history feature (button).
   *
   * @returns {boolean}
   *   TRUE if feature was blocked, FALSE otherwise.
   */
  processRevisionDisable() {
    if (!this.isCollaborationDisabled()) {
      return false;
    }

    if (this.editor.plugins.has( 'RevisionTracker' )) {

      this.editor.plugins.get( 'RevisionTracker' ).isEnabled = false;
    }

    return true;
  }

  /**
   * Checks if collaboration is set to be disabled.
   *
   * @returns {boolean}
   *   TRUE if conditions for blocking collaboration are met, FALSE otherwise.
   */
  isCollaborationDisabled() {
    return typeof drupalSettings.ckeditor5Premium != 'undefined' &&
      typeof drupalSettings.ckeditor5Premium.disableCollaboration != "undefined" &&
      drupalSettings.ckeditor5Premium.disableCollaboration === true;
  }

  /**
   * Returns parent element of an editors' element matching passed ID.
   *
   * @param elementId
   *   HTML ID of an editor.
   *
   * @returns {HTMLElement|null}
   */
  getEditorParentContainer(elementId) {
    let editorElement = document.getElementById(elementId);

    while (editorElement && typeof editorElement !== "undefined"
      && typeof editorElement.classList !== "undefined" &&
      !editorElement.classList.contains('ck-editor-container')) {

      editorElement = editorElement.parentElement;
    }

    if (!editorElement || typeof editorElement === "undefined") {
      return null;
    }

    // We get parentElement one more time to be able to search for all related
    // editor elements (like sidebar, presence list etc)
    return editorElement.parentElement;
  }

  getSourceDataSelector(type) {
    const types = {
      'trackChanges': '.track-changes',
      'comments': '.comments',
      'revisionHistory': '.revision-history',
      'revisionHistoryContainer': '.revision-history-container',
      'resolvedSuggestionsComments': '.resolved-suggestions-comments',
    };

    const cssClass = types[type] + '-data';
    const dataAttribute = `[data-ckeditor5-premium-element-id="${this.elementId}"]`;

    return cssClass + dataAttribute;
  }
}

export default CollaborationStorage;
