/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

import CollaborationStorage from "../../collaborationStorage/src/collaborationStorage.js";
import {ButtonView} from "ckeditor5/src/ui";

class SidebarAdapter {

  static SIDEBAR_ICON = '<svg viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg" style="transform: scale(-1,1)"><path d="M5 9.5a.5.5 0 0 0 .5-.5v-.5A.5.5 0 0 0 5 8H3.5a.5.5 0 0 0-.5.5V9a.5.5 0 0 0 .5.5H5Z"/><path d="M5.5 12a.5.5 0 0 1-.5.5H3.5A.5.5 0 0 1 3 12v-.5a.5.5 0 0 1 .5-.5H5a.5.5 0 0 1 .5.5v.5Z"/><path d="M5 6.5a.5.5 0 0 0 .5-.5v-.5A.5.5 0 0 0 5 5H3.5a.5.5 0 0 0-.5.5V6a.5.5 0 0 0 .5.5H5Z"/><path clip-rule="evenodd" d="M2 19a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H2Zm6-1.5h10a.5.5 0 0 0 .5-.5V3a.5.5 0 0 0-.5-.5H8v15Zm-1.5-15H2a.5.5 0 0 0-.5.5v14a.5.5 0 0 0 .5.5h4.5v-15Z"/></svg>';
  static COLLAPSE_ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 21 21"><path d="M9.064 14.666a.75.75 0 1 1-1.06-1.06L11.01 10.6 8.004 7.595a.75.75 0 1 1 1.06-1.06l3.55 3.549a.748.748 0 0 1-.136 1.168z"/></svg>';

  constructor( editor ) {
    this.editor = editor;
    this.storage = new CollaborationStorage(editor);
    this.toolbar = this.editor.ui._toolbarConfig.items
    this.sidebarMode = drupalSettings.ckeditor5SidebarMode ?? 'auto';
    this.resizeThreshold = 0;

    if (typeof this.editor.sourceElement === "undefined") {
      return;
    }
    let sidebar_column = this.getSidebarWrapper(this.editor.sourceElement.id);

    if (typeof sidebar_column === 'undefined' || !sidebar_column) {
      return;
    }
    this.sidebarColumn = sidebar_column;
    this.sidebar = sidebar_column.parentElement;
    this.editorContainer = this.sidebar.parentElement;

    this.editor.config._config.sidebar = {
      container: sidebar_column,
      preventScrollOutOfView: drupalSettings.ckeditor5Premium.preventScrollOutOfView,
    }
  }

  static get pluginName() {
    return 'SidebarAdapter'
  }

  sidebarVisibilityModify(hide = false) {
    if (!this.sidebar || typeof this.sidebar === 'undefined') {
      return;
    }
    this.sidebar.classList.toggle('slider-off', hide);
  }

  init() {
    if (!this.sidebar || !this.editor.plugins.has('AnnotationsUIs')) {
      return;
    }

    this.addToggleButton();
  }

  afterInit() {
    if (!this.annotationsUIs || typeof this.annotationsUIs === "undefined" ||
      !this.sidebar || typeof this.sidebar === 'undefined') {
      return;
    }

    let sidebarHide = !this.toolbar.includes('trackChanges') && !this.toolbar.includes('comment') || this.storage.isCollaborationDisabled();

    this.sidebarVisibilityModify(sidebarHide);

    this.handleSidebarMode();

    if (this.editor.config._config.sidebar.preventScrollOutOfView) {
      this.sidebarColumn.classList.add('prevent-scroll-out-of-view');
    }

    this.editor.on('ready', () => {
      this.setScrollBarObservers();

      if (this.editor.ui.view.element) {
        this.editor.ui.view.element.classList += ' ck-sidebar-enabled';
      }
    });

  }

  destroy() {
    if (!this.annotationsUIs || typeof this.annotationsUIs === "undefined" ||
      !this.sidebar || typeof this.sidebar === 'undefined') {
      return;
    }

    if (this.resizeObserver) {
      this.resizeObserver.disconnect();
    }

    this.viewElementScrollbarObserver.disconnect();

    this.sidebarVisibilityModify(true);
    let toggle = this.getSidebarToggleWrapper()
    if (toggle) {
      toggle.remove();
    }
  }

  setToggleButtonState() {
    if ( this.sidebar.classList.contains( 'narrowSidebar' ) ) {
      this.toggleButton.set( {
        label: 'Toggle sidebar',
        class: 'ck-sidebar-auto-toggle',
        tooltip: 'Switch to wide sidebar mode',
        tooltipPosition: 'se',
        icon: SidebarAdapter.SIDEBAR_ICON
      } );
    } else {
      this.toggleButton.set( {
        label: 'Toggle sidebar',
        class: 'ck-sidebar-auto-toggle',
        tooltip: 'Switch to narrow sidebar mode',
        tooltipPosition: 'se',
        icon: SidebarAdapter.COLLAPSE_ICON
      } );
    }
  }

  /**
   * Set the sidebar toggle button.
   */
  addToggleButton() {

    this.toggleButton = new ButtonView( this.editor.locale );
    this.setToggleButtonState();

    this.toggleButton.on( 'execute', () => {
      // Change the look of the button to reflect the state of the outline.
      if ( this.sidebar.classList.contains( 'narrowSidebar' ) ) {
        this.sidebar.classList.remove('narrowSidebar');
        this.sidebar.classList.add('wideSidebar');
        this.setToggleButtonState();
        this.setCkEditorSidebarMode('wideSidebar');

      } else {
        this.sidebar.classList.remove('wideSidebar');
        this.sidebar.classList.add('narrowSidebar');
        this.setToggleButtonState();
        this.setCkEditorSidebarMode('narrowSidebar');
      }

      // Keep the focus in the editor whenever the button is clicked.
      this.editor.editing.view.focus();
    } );

    this.toggleButton.render();

    // Toggle wrapper.
    this.toggleWrapper = document.createElement('div');
    this.toggleWrapper.classList.add('ck-sidebar-auto-toggle-wrapper');

    // Append the button next to the outline in its container and toggle wrapper.
    this.toggleWrapper.prepend( this.toggleButton.element );
    this.sidebar.prepend(this.toggleWrapper);

    this.annotationsUIs = this.editor.plugins.get('AnnotationsUIs');
  }

  /**
   * Set margin on toggle wrapper, so the toggle doesn't cover sidebar if it is visible.
   */
  setScrollBarObservers() {
    this.viewElementScrollbarObserver = new ResizeObserver(entries => {
      const baseMargin = -37;
      const scrollbarWidth = entries[0].target.offsetWidth - entries[0].target.clientWidth;
      const totalMargin = baseMargin - scrollbarWidth;
      this.toggleWrapper.style.marginLeft = totalMargin + "px";
    });
    this.viewElementScrollbarObserver.observe(this.editor.ui.view.editable.element);
  }

  /**
   * Search sidebar element near the element with provided ID.
   *
   * @param elementId
   *   Editor related tag ID.
   *
   * @returns {null|Element}
   *   Sidebar tag or NULL if tag not found.
   */
  getSidebarWrapper(elementId) {
    let editorParent = this.storage.getEditorParentContainer(elementId);

    if (!editorParent) {
      return null;
    }

    return editorParent.querySelector('.ck-sidebar-wrapper');
  }

  /**
   * Checks sidebar mode setting and attaches event listeners if required.
   */
  handleSidebarMode() {
    let toggle = this.getSidebarToggleWrapper();
    let prevWidth = 0;

    // Set the resize observer
    this.resizeObserver = new ResizeObserver((entries) => {
      for (const entry of entries) {
        const width = entry.borderBoxSize?.[0].inlineSize;
        clearTimeout(this.resizeThreshold);
        this.resizeThreshold = setTimeout(() => {
          if (typeof width === 'number' && width !== prevWidth) {
            prevWidth = width;
            if (this.sidebarMode !== 'auto') {
              this.setCkEditorSidebarMode(this.sidebarMode);
            } else {
              this.updateCkeditorMode();
            }
          }
        }, 100);
      }
    });

    this.resizeObserver.observe(this.editorContainer);

    if (this.sidebarMode !== 'auto') {
      this.setCkEditorSidebarMode(this.sidebarMode);
      if (toggle) {
        toggle.style.display = 'none';
      }
      return;
    }

    this.updateCkeditorMode();

    if (!toggle) {
      return;
    }

  }

  /**
   * Returns a toggle button wrapper for handled sidebar or null if not found.
   *
   * @returns {null|Element}
   */
  getSidebarToggleWrapper() {
    if (!this.sidebar || typeof this.sidebar === 'undefined') {
      return null;
    }
    return this.sidebar.querySelector(".ck-sidebar-auto-toggle-wrapper");
  }

  /**
   * Setup new sidebar mode.
   *
   * @param newMode
   *   Sidebar mode to setup.
   */
  setCkEditorSidebarMode(newMode) {
    if (!this.sidebar || typeof this.sidebar === 'undefined') {
      return;
    }
    if (this.sidebar.classList.contains('manual-toggled') && newMode === 'wideSidebar') {
      if (this.annotationsUIs.isActive('inline') || this.annotationsUIs.isActive('wideSidebar')) {
        newMode = 'narrowSidebar';
      } else {
        return;
      }
    }

    this.sidebar.classList.remove('inline', 'narrowSidebar', 'wideSidebar');
    this.annotationsUIs.switchTo(newMode);
    this.sidebar.classList.add(newMode);

    this.setToggleButtonState();
  }

  /**
   * Setup sidebar mode depends on resolution.
   */
  updateCkeditorMode() {
    // TODO: move to config?
    let w = this.editorContainer.clientWidth;
    if (w === 0) {
      return;
    }
    let newMode = w >= 720 ? 'wideSidebar' : (w >= 500 ? 'narrowSidebar' : 'inline');
    this.setCkEditorSidebarMode(newMode);
  }

}

export default SidebarAdapter;
