
/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

import CollaborationStorage
  from "../../../../../../js/ckeditor5_plugins/collaborationStorage/src/collaborationStorage.js";

class RevisionHistoryAdapter {
  constructor( editor ) {
    this.editor = editor;
    this.storage = new CollaborationStorage(editor);
  }

  static get pluginName() {
    return 'RevisionHistoryAdapter'
  }

  static get requires() {
    return [ 'RevisionHistory', 'RevisionTracker' ]
  }

  afterInit() {
    if (this.storage.processRevisionDisable()) {
      return;
    }

    // Initialize revision history settings.
    if (typeof drupalSettings.ckeditor5Premium == "undefined") {
      return;
    }

    const addRevisionOnSubmit = drupalSettings.ckeditor5Premium.addRevisionOnSubmit ?? false;
    const revisionHistoryConfig = this.editor.config._config.revisionHistory;
    let revisionHistoryContainer = document.querySelector(this.storage.getSourceDataSelector('revisionHistoryContainer'));
    if (revisionHistoryContainer === null) {
      revisionHistoryContainer = revisionHistoryConfig.viewerEditorElement.parentNode;
    }

    revisionHistoryConfig.viewerContainer = revisionHistoryContainer;
    revisionHistoryConfig.viewerEditorElement = revisionHistoryContainer.querySelector('.revision-viewer-editor');
    revisionHistoryConfig.viewerSidebarContainer = revisionHistoryContainer.querySelector('.revision-viewer-sidebar');
    revisionHistoryConfig.editorContainer = revisionHistoryContainer.parentNode.querySelector('.ck-editor-premium-wrapper').parentNode;

    // Initialize plugin.
    const revisionHistoryPlugin = this.editor.plugins.get('RevisionHistory');
    const revisionTrackerPlugin = this.editor.plugins.get('RevisionTracker');
    const revisionHistoryElement = document.querySelector(this.storage.getSourceDataSelector('revisionHistory'));

    // Load revisions.
    const revisions = JSON.parse(revisionHistoryElement.value);
    let create_new_draft = false;

    for (const revisionData of revisions) {
      if (revisionData['attributes']['new_draft_req']) {
        create_new_draft = true;
        delete revisionData['attributes']['new_draft_req'];
      }
      const revision = revisionHistoryPlugin.addRevisionData(revisionData);
      revision.on('change', (eventInfo, name, value, oldValue) => {
        this.updateStorage(revisionHistoryPlugin, revisionTrackerPlugin, revisionHistoryElement, false);
      });
    }

    if (create_new_draft) {
      setTimeout(() => {
        this.updateStorage(revisionHistoryPlugin, revisionTrackerPlugin, revisionHistoryElement, addRevisionOnSubmit);
      }, 10);
    }

    // Hook to form submit.
    const form = this.editor.sourceElement.closest('form');
    form.addEventListener("submit", (e) => {
      this.updateStorage(revisionHistoryPlugin, revisionTrackerPlugin, revisionHistoryElement, addRevisionOnSubmit)
    });

    // Prevent form submission if pointer type is unknown.
    form.addEventListener("click", (e) => {
      if (e.pointerType.length === 0 && revisionHistoryPlugin.isRevisionViewerOpen) {
        e.preventDefault();
      }
    });

    this.editor.model.document.on( 'change:data', () => {
      this.updateStorage(revisionHistoryPlugin, revisionTrackerPlugin, revisionHistoryElement, false)
    });

    this.handleRevisionHistoryActiveClass(revisionHistoryConfig);
  }

  async updateStorage(plugin, tracker, storageElement, addRevisionOnSubmit) {
    await tracker.update();
    if (addRevisionOnSubmit) {
      await tracker.saveRevision({name: 'Entity save'});
    }
    storageElement.value = JSON.stringify(plugin.getRevisions({
      toJSON: true
    }));
  }

  handleRevisionHistoryActiveClass(revisionHistoryConfig) {
    var observer = new IntersectionObserver(function(entries) {
      if(entries[0]['intersectionRatio'] == 0) {
        revisionHistoryConfig.editorContainer.parentElement.classList.add('revision-history-active');
      }
      else {
        revisionHistoryConfig.editorContainer.parentElement.classList.remove('revision-history-active');
      }
    }, { root: document.documentElement });

    observer.observe(revisionHistoryConfig.editorContainer);
  }
}

export default RevisionHistoryAdapter;
