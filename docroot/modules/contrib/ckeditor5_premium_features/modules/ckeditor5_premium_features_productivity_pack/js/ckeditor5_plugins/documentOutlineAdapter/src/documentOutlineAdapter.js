
/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

/* global document */

import {ButtonView} from "ckeditor5/src/ui";

export default class DocumentOutlineAdapter {

  constructor( editor ) {
    this.editor = editor;

    if (typeof this.editor.sourceElement === "undefined") {
      return;
    }

    this.elementId = this.editor.sourceElement.dataset.drupalSelector;

    const documentOutlineId = this.elementId + '-ck-document-outline';
    this.documentOutlineContainer = document.getElementById(documentOutlineId);

    if (typeof this.documentOutlineContainer === 'undefined' || !this.documentOutlineContainer || this.isDocumentOutlineDisabled()) {
      return;
    }

    // Clear container contents to ensure there are no duplicated DO contents
    // when text format is changed.
    this.documentOutlineContainer.innerHTML = "";

    const DOCUMENT_OUTLINE_ICON = '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M5 9.5a.5.5 0 0 0 .5-.5v-.5A.5.5 0 0 0 5 8H3.5a.5.5 0 0 0-.5.5V9a.5.5 0 0 0 .5.5H5Z"/><path d="M5.5 12a.5.5 0 0 1-.5.5H3.5A.5.5 0 0 1 3 12v-.5a.5.5 0 0 1 .5-.5H5a.5.5 0 0 1 .5.5v.5Z"/><path d="M5 6.5a.5.5 0 0 0 .5-.5v-.5A.5.5 0 0 0 5 5H3.5a.5.5 0 0 0-.5.5V6a.5.5 0 0 0 .5.5H5Z"/><path clip-rule="evenodd" d="M2 19a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H2Zm6-1.5h10a.5.5 0 0 0 .5-.5V3a.5.5 0 0 0-.5-.5H8v15Zm-1.5-15H2a.5.5 0 0 0-.5.5v14a.5.5 0 0 0 .5.5h4.5v-15Z"/></svg>';
    const COLLAPSE_OUTLINE_ICON = '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M11.463 5.187a.888.888 0 1 1 1.254 1.255L9.16 10l3.557 3.557a.888.888 0 1 1-1.254 1.255L7.26 10.61a.888.888 0 0 1 .16-1.382l4.043-4.042z"/></svg>';
    const button = new ButtonView( editor.locale );

    button.set( {
      label: 'Toggle document outline',
      class: 'ck-document-outline-toggle',
      tooltip: 'Show document outline',
      tooltipPosition: 'se',
      icon: DOCUMENT_OUTLINE_ICON
    } );

    button.on( 'execute', () => {
      // Toggle a CSS class on the demo container to manage the visibility of the outline.
      this.documentOutlineContainer.classList.toggle( 'collapsed' );

      // Change the look of the button to reflect the state of the outline.
      if ( this.documentOutlineContainer.classList.contains( 'collapsed' ) ) {
        button.icon = DOCUMENT_OUTLINE_ICON;
        button.tooltip = 'Show document outline';
      } else {
        button.icon = COLLAPSE_OUTLINE_ICON;
        button.tooltip = 'Hide document outline';
      }

      // Keep the focus in the editor whenever the button is clicked.
      editor.editing.view.focus();
    } );

    button.render();

    // Toggle wrapper.
    let wrapper = document.createElement('div');
    wrapper.classList.add('ck-document-outline-toggle-wrapper');

    // Append the button next to the outline in its container and toggle wrapper.
    wrapper.appendChild( button.element );
    this.documentOutlineContainer.appendChild( wrapper );

    editor.config._config.documentOutline = {'container': this.documentOutlineContainer};

    this.containerVisibilityModify(false);
  }

  static get pluginName() {
    return 'DocumentOutlineAdapter';
  }

  init() {

  }

  destroy() {
    this.containerVisibilityModify(true);
  }

  containerVisibilityModify(hide = false) {
    if (!this.documentOutlineContainer || typeof this.documentOutlineContainer === 'undefined') {
      return;
    }
    this.documentOutlineContainer.classList.toggle('hidden', hide);
  }

  isDocumentOutlineDisabled() {
    if (this.editor.config._config.removePlugins) {
      return this.editor.config._config.removePlugins.includes("DocumentOutline");
    }
    return false;
  }

}
