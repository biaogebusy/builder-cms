
/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

import CollaborationStorage
  from "../../../../../../js/ckeditor5_plugins/collaborationStorage/src/collaborationStorage.js";

class TrackChangesAdapter {
  trackedSuggestion;

  constructor( editor ) {
    this.editor = editor;
    this.storage = new CollaborationStorage(editor);
  }

  static get pluginName() {
    return 'TrackChangesAdapter'
  }

  static get requires() {
    return [ 'TrackChanges', 'Comments', 'TrackChangesAdapter' ]
  }

  afterInit() {
    if (!this.editor.plugins.has('Comments') || !this.editor.plugins.has('TrackChanges')) {
      return
    }

    if (this.storage.processCollaborationCommandDisable("trackChanges")) {
      return;
    }

    const trackChangesPlugin = this.editor.plugins.get( 'TrackChanges' );
    const trackChangesElement = document.querySelector(this.storage.getSourceDataSelector('trackChanges'));

    if (!trackChangesElement || trackChangesElement.value == '') {
      return
    }

    this.editor.on('ready', () => {
      let textFormat = this.editor.sourceElement.dataset.editorActiveTextFormat;
      let isTrackingChangesOn = drupalSettings.ckeditor5Premium.tracking_changes.default_state;
      if (typeof isTrackingChangesOn[textFormat] !== 'undefined' && isTrackingChangesOn[textFormat]) {
        this.editor.execute('trackChanges');
      }
    });

    this.trackedSuggestion = new Map();

    // Load suggestions.
    const suggestions = JSON.parse(trackChangesElement.value);
    for (const suggestion of suggestions) {
      trackChangesPlugin.addSuggestion(suggestion);
      this.attachSuggestionEvents(trackChangesPlugin.getSuggestion(suggestion.id));
    }

    // Hook to form submit.
    const form = this.editor.sourceElement.closest('form');
    form.addEventListener("submit", () => {
      this.updateStorage(trackChangesPlugin, trackChangesElement);
    });
  }

  updateStorage(plugin, storageElement) {
    // We collect all suggestions, because we need to pass them to the backend
    // in order to be able to delete some of them.
    var suggestions = plugin.getSuggestions({skipNotAttached: false});

    for (let i in suggestions) {
      if (suggestions[i].head != null && (suggestions[i].next != null || suggestions[i].previous != null) ) {
        suggestions[i].setAttribute('head', suggestions[i].head.id);
      }
      if (this.trackedSuggestion.has(suggestions[i].id)) {
        if (suggestions[i].isInContent == true) {
          suggestions[i].removeAttribute('status');
        } else {
          if (typeof suggestions[i].attributes.status === 'undefined') {
            this.trackedSuggestion.delete(suggestions[i].id)
          }
        }
        continue;
      }
      if (suggestions[i].isInContent == false) {
        // Here we have a case of suggestion that was accepted/rejected before storing in DB.
        this.editor.model.document.fire('trackchanges:change:data');
        continue;
      }

      this.attachSuggestionEvents(suggestions[i]);
    }

    storageElement.value = JSON.stringify(Array.from(this.trackedSuggestion.values()));
  };

  attachSuggestionEvents(suggestion) {
    this.trackedSuggestion.set(suggestion.id, suggestion);
    var self = this;

    var suggestionStatusUpdate = function (event) {
      let suggestionTracked = self.trackedSuggestion.get(event.source.id);
      if (event.name === 'accept' || event.name === 'discard') {
        self.updateSuggestionCommentsData(event)
      }
      if (typeof suggestionTracked == "undefined") {
        return;
      }
      if (typeof suggestionTracked.attributes == "undefined" ||
        typeof suggestionTracked.attributes.key == "undefined") {
        self.trackedSuggestion.delete(event.source.id);
        return;
      }
      suggestionTracked.setAttribute('status', event.name);
    }

    suggestion.on('accept', suggestionStatusUpdate, {priority: 'highest'});
    suggestion.on('discard', suggestionStatusUpdate, {priority: 'highest'});
  }

  updateSuggestionCommentsData(data) {
    const commentsRepositoryPlugin = this.editor.plugins.get('CommentsRepository');
    const resolvedSuggestionsCommentsElement = document.querySelector(this.storage.getSourceDataSelector('resolvedSuggestionsComments'));
    let commentId = data.source.id;
    let values = resolvedSuggestionsCommentsElement.value;
    if (typeof commentId === 'undefined' || !commentId) {
      return;
    }
    if (!values) {
      values = JSON.stringify([]);
    }

    let dataArray = Array.from(JSON.parse(values))
    let thread = commentsRepositoryPlugin.getCommentThread(commentId).toJSON();

    const isExisting = dataArray.some(element => {
      return element.threadId === thread.threadId;
    });

    if (!isExisting && thread.comments.length) {
      dataArray.push(thread);
      resolvedSuggestionsCommentsElement.value =  JSON.stringify(dataArray);
    }
  };

}

export default TrackChangesAdapter;
