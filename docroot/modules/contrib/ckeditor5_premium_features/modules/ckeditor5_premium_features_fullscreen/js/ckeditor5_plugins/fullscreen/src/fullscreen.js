/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';
import fullscreenIcon from './theme/icons/fullscreen.svg';

/* global document */

export default class FullScreen extends Plugin {

	static get pluginName() {
		return 'FullScreen';
	}

	init() {
		const editor = this.editor;
    let fullScreenEnabled = false;
    const sourceEditing = this.editor.plugins.has('SourceEditing') ? this.editor.plugins.get('SourceEditing') : null;

		editor.ui.componentFactory.add( 'FullScreen', locale => {
			const overlayClass = 'ck-fullscreen-overlay';
      let additionalClasses = [];
			const editorFullScreenClass = 'ck-fullscreen';
			const view = new ButtonView( locale );
      let defaultOffsetTop = null;
			// @todo keystroke
			view.set( {
				label: 'Maximize',
				icon: fullscreenIcon,
				tooltip: true,
				isToggleable: true
			} );

			view.on( 'execute', () => {
				const editorWrapper = editor.sourceElement.closest( '.ck-editor-premium-wrapper' );
				const sourceElementSibling = editor.sourceElement.nextElementSibling;
				const targetElement = editorWrapper ? editorWrapper : sourceElementSibling;
				const revHistoryElement = targetElement.parentNode.parentNode.querySelector( '.revision-history-container-data' );
        const stickyPanel = targetElement.querySelector('.ck-sticky-panel__content');
        const stickyPanelPlaceholder = targetElement.querySelector('.ck-sticky-panel__placeholder');
        const toolbar = targetElement.querySelector('.ck-toolbar');
        const wordCountId = editor.sourceElement.id + "-ck-word-count";
        const wordCount = document.querySelector("#" + wordCountId + " .ck-word-count");
        let wordCountHeight = wordCount === null ? 0 : wordCount.offsetHeight + 1;
        if (wordCount) {
          additionalClasses.push('ck-fullscreen-with-word-count');
        }
        let container = targetElement.querySelector('.ck-editor-container');
        if (!container) {
          container = targetElement.querySelector('.ck-editor__editable').parentNode;
        }
        if (sourceEditing && wordCount) {
          sourceEditing.on( 'change:isSourceEditingMode', () => {
            if (fullScreenEnabled) {
              wordCountHeight = wordCount === null ? 0 : wordCount.offsetHeight;
              container.style.height = "calc(100vh - " + (toolbarHeight + wordCountHeight + 2) + "px";
            }
          });
        }
        if ( document.body.classList.contains( overlayClass ) ) {
					targetElement.classList.remove( editorFullScreenClass );
					if ( revHistoryElement ) {
						revHistoryElement.classList.remove( editorFullScreenClass );
					}
          if ( wordCount ) {
            wordCount.classList.remove( editorFullScreenClass );
          }
          document.body.classList.remove( overlayClass, ...additionalClasses );
          container.style.height = null;
					view.set( 'label', 'Maximize' );
					view.set( 'isOn', false );
          editor.ui.view.stickyPanel.set('isActive', true);
          stickyPanel.classList.remove('ck-sticky-panel__content_sticky');
          stickyPanel.removeAttribute('style');
          stickyPanelPlaceholder.style.display = 'none';
          editor.focus();
          fullScreenEnabled = false;
          this.editor.ui.viewportOffset.top = defaultOffsetTop;
				}
				else {
					targetElement.classList.add( editorFullScreenClass );
					if ( revHistoryElement ) {
						revHistoryElement.classList.add( editorFullScreenClass );
					}
          if ( wordCount ) {
            wordCount.classList.add( editorFullScreenClass );
          }
          document.body.classList.add( overlayClass, ...additionalClasses );
          const toolbarHeight = toolbar ? toolbar.offsetHeight : 0;
          container.style.height = "calc(100vh - " + (toolbarHeight + wordCountHeight + 2) + "px";
					view.set( 'label', 'Minimize' );
					view.set( 'isOn', true );
          editor.ui.view.stickyPanel.set('isActive', false);
          editor.focus();
          fullScreenEnabled = true;

          // viewportOffset.top can block display of balloon in case it's top property value is lower.
          // That happens because CKEditor limits possibility to display balloon to be within body element,
          // which can have padding added due to admin toolbar. In such case first line of fullscreen editor
          // can be positioned outside the body element.
          // We're fixing this issue by temporarily changing viewportOffset.top value for when fullscreen mode is active.
          defaultOffsetTop = this.editor.ui.viewportOffset.top;
          editor.ui.viewportOffset.top = 0;
				}
			} );
			return view;
		} );
    this.editor.ui.on('set', (eventInfo, name, value, oldValue) => {
      // Prevent changing viewportOffset.top value back to original value when viewport size is changed when fullscreen
      // mode is active.
      if (fullScreenEnabled) {
        value.top = 0;
      }
    });
	}
}
