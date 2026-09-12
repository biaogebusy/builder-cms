/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

import CollaborationStorage
  from "../../../../../../js/ckeditor5_plugins/collaborationStorage/src/collaborationStorage.js";

class RealtimeAdapter {
  constructor(editor) {
    this.editor = editor;
    this.submitElements = [];
    this.formElement = this.editor.sourceElement.closest('form');
    this.disabledAttributeName = 'data-ckeditor5-block-' + this.editor.id;

    if (this.editor.config._config.realtime.readonly) {
      this.editor.enableReadOnlyMode('realtime');
      return;
    }

    if (typeof this.editor.sourceElement === "undefined") {
      return;
    }

    this.storage = new CollaborationStorage(editor);

    if (typeof drupalSettings.ckeditor5ChannelId == "undefined" ||
      typeof this.editor.sourceElement.dataset.ckeditorfieldid == "undefined" ||
      typeof drupalSettings.ckeditor5ChannelId[this.editor.sourceElement.dataset.ckeditorfieldid] == "undefined") {
      return;
    }
    this.editor.config._config.collaboration = {
      channelId: drupalSettings.ckeditor5ChannelId[this.editor.sourceElement.dataset.ckeditorfieldid],
    }
    this.setPresenceListContainer();

    this.disableSubmitButtons();
  }

  static get pluginName() {
    return 'RealtimeAdapter'
  }

  init() {
    if (this.editor.config._config.realtime.readonly) {
      return;
    }
    if (typeof this.editor.sourceElement === "undefined") {
      return;
    }

    const editor = this.editor;
    const hasRTC = editor.plugins.has('RealTimeCollaborativeEditing');
    const hasSourceEditing = editor.plugins.has('SourceEditing') || editor.plugins.has('SourceEditingEnhanced');
    if (hasRTC && hasSourceEditing) {
      console.info('The Source editing plugin is not compatible with real-time collaboration, so it has been disabled. If you need it, please contact us to discuss your use case - https://ckeditor.com/contact/');
      if (editor.plugins.has('SourceEditing')) {
        editor.plugins.get('SourceEditing').forceDisabled('drupal-rtc');
      }
      if (editor.plugins.has('SourceEditingAdvanced')) {
        editor.plugins.get('SourceEditingAdvanced').forceDisabled('drupal-rtc');
      }
    }

    let editorParent = this.getFieldWrapper(this.editor.sourceElement.id);
    if (editorParent) {
      this.textFormatSelect = editorParent.querySelector(".js-filter-list");
      if (this.textFormatSelect) {
        this.textFormatSelect.addEventListener('change', this.changeEditor.bind(this));
      }
    }

  }

  getFieldWrapper(elementId) {
    const editorElement = document.getElementById(elementId);
    if (editorElement) {
      return editorElement.closest(".js-text-format-wrapper");
    }
    return null;
  }

  /**
   * Calls an endpoint that resets the collaborative session for a channel of the field that has text format changed.
   *
   * @param event
   */
  changeEditor(event) {
    const channelId = this.editor.config._config.collaboration.channelId
    const Http = new XMLHttpRequest();
    const url='/ckeditor5-premium-features-realtime-collaboration/flush-session/' + channelId;
    Http.open("DELETE", url);
    Http.send();
  }

  setPresenceListContainer() {
    const presenceListConfig = this.editor.config._config.presenceList;
    if (!presenceListConfig || typeof presenceListConfig === "undefined") {
      return;
    }
    if (!presenceListConfig.container) {
      const presenceListContainerId = this.editor.sourceElement.id + '-presence-list-container';
      const presenceListElement = document.getElementById(presenceListContainerId);
      if (!presenceListElement) {
        const formItem = this.editor.sourceElement.closest(".form-item");
        const presenceListWrapper = document.createElement("div");
        presenceListWrapper.setAttribute("class", "ck-presence-list-container");
        presenceListWrapper.setAttribute("id", presenceListContainerId);
        formItem.parentNode.insertBefore(presenceListWrapper, formItem.previousSibling);
        presenceListConfig.container = presenceListWrapper;
      } else {
        presenceListConfig.container = presenceListElement;
      }
    }

    if (!presenceListConfig.collapseAt) {
      presenceListConfig.collapseAt = drupalSettings.presenceListCollapseAt;
    }
  }

  clearPresenceListContainer() {
    const presenceListContainer = this.editor.config._config.presenceList.container;
    if (presenceListContainer) {
      presenceListContainer.innerHTML = '';
    }
  }

  /**
   * Executed after plugin is initialized.
   *
   * For the RTC it's the most suitable place to dynamically disable toolbar
   * items.
   */
  afterInit() {
    if (this.editor.config._config.realtime.readonly) {
      return;
    }
    if (typeof this.editor.sourceElement === "undefined") {
      return;
    }

    this.storage.processCollaborationCommandDisable("trackChanges");
    this.storage.processCollaborationCommandDisable("addCommentThread");
    this.checkIfInitialDataChanged();

    if (drupalSettings.ckeditor5Premium.notificationsEnabled) {
      // Hook to form submit.
      const form = this.editor.sourceElement.closest('form');
      form.addEventListener("submit", () => {
        const isCommentsEnabled = this.editor.plugins.has('CommentsRepository');
        const isTrackChangesEnabled = this.editor.plugins.has('TrackChanges');
        const isInstantRtcCommentsEnabled = this.editor.plugins.has('RealtimeCommentNotifications');
        if (!isCommentsEnabled || !isTrackChangesEnabled) {
          return
        }

        const elementId = this.editor.sourceElement.dataset.ckeditor5PremiumElementId
        const types = {
          'trackChanges': '.track-changes',
          'comments': '.comments',
        };
        const dataAttribute = `[data-ckeditor5-premium-element-id="${elementId}"]`;

        if (isTrackChangesEnabled) {
          let trackedSuggestion = new Map()
          const trackChangesCssClass = types['trackChanges'] + '-data';
          const trackChangesPlugin = this.editor.plugins.get( 'TrackChanges' );
          const suggestions = trackChangesPlugin.getSuggestions({skipNotAttached: false});
          const trackChangesElement = document.querySelector(trackChangesCssClass + dataAttribute);
          for (let i in suggestions) {
            // Clone suggestion before adding modifications to attributes as this may break grouped suggestions.
            let clone = this.cloneSuggestionForBackend(suggestions[i]);
            if (suggestions[i].head != null && (suggestions[i].next != null || suggestions[i].previous != null)) {
              clone.attributes.head = suggestions[i].head;
            }
            clone.attributes.items = suggestions[i].getItems();
            trackedSuggestion.set(suggestions[i].id, suggestions[i]);
          }
          trackChangesElement.value = JSON.stringify(Array.from(trackedSuggestion.values()));
        }

        if (isCommentsEnabled && !isInstantRtcCommentsEnabled) {
          const commentsCssClass = types['comments'] + '-data';
          const commentsRepositoryPlugin = this.editor.plugins.get( 'CommentsRepository' );
          const commentsElement = document.querySelector(commentsCssClass + dataAttribute);
          commentsElement.value = JSON.stringify(commentsRepositoryPlugin.getCommentThreads({
            skipNotAttached: true,
            skipEmpty: true,
            toJSON: true
          }));
        }
      });
    }

    this.editor.on('ready', () => {
      let textFormat = this.editor.sourceElement.dataset.editorActiveTextFormat;
      let isTrackingChangesOn = drupalSettings.ckeditor5Premium.tracking_changes.default_state;
      if (typeof isTrackingChangesOn[textFormat] !== 'undefined' && isTrackingChangesOn[textFormat]) {
        this.editor.execute('trackChanges');
      }

      this.enableSubmitButtons();
    });
  }

  destroy() {
    if (this.editor.config._config.realtime.readonly) {
      return;
    }
    if (this.textFormatSelect != null) {
      this.textFormatSelect.removeEventListener('change', this.changeEditor.bind(this));
    }
    this.clearPresenceListContainer();
    this.submitElements = [];
  }

  /**
   *  Check if the editor's initial data is different from the data from CS.
   *  If so, set "data-editor-value-is-changed" attribute to TRUE.
   */
  checkIfInitialDataChanged() {
    const initialData = this.editor.config._config.initialData;
    this.editor.on('ready', () => {
      if (initialData !== this.editor.getData()) {
        this.editor.sourceElement.setAttribute('data-editor-value-is-changed', true);
      }
    } );
  }

  /**
   * Disables all submit buttons in the form that contains the editor.
   */
  disableSubmitButtons() {
    if (!this.formElement) {
      return;
    }

    this.submitElements = this.formElement.querySelectorAll('input[type="submit"], button[type="submit"]');

    Array.from(this.submitElements).forEach(element => {
      element.disabled = true;
      element.setAttribute(this.disabledAttributeName, true);
    });
  }

  /**
   * Remove lock on submit buttons in the form that contains the editor.
   * Re-enable the buttons if there is no more locks applied.
   */
  enableSubmitButtons() {
    if (!this.formElement) {
      return;
    }

    Array.from(this.submitElements).forEach(element => {
      element.removeAttribute(this.disabledAttributeName);
      if (!this.hasDataBlockedAttribute(element)) {
        element.disabled = false;
      }
    });
  }

  /**
   * Checks if an element is blocked by CKEditor still being loaded'
   * @param {HTMLElement} element - The DOM element to check
   * @returns {boolean} - True if such attributes exist, false otherwise
   */
  hasDataBlockedAttribute(element) {
    if (!element || !element.hasAttributes()) {
      return false;
    }

    for (let attr of element.attributes) {
      if (attr.name.startsWith('data-ckeditor5-block-')) {
        return true;
      }
    }

    return false;
  }

  cloneSuggestionForBackend(suggestion) {
    let clone = {
      'id': suggestion.id,
      'type': suggestion.type,
      'authorId': suggestion.authorId,
      'createdAt': suggestion.createdAt,
      'hasComments': suggestion.hasComments,
      'data': suggestion.data,
      'attributes': suggestion.attributes,
    };

    return clone
  }

}

export default RealtimeAdapter;
