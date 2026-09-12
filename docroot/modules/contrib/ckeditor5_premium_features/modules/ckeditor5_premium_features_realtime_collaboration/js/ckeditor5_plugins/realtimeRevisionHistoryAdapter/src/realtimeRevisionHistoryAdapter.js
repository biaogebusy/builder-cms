/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

import CollaborationStorage
  from "../../../../../../js/ckeditor5_plugins/collaborationStorage/src/collaborationStorage.js";


class RealtimeRevisionHistoryAdapter {
  constructor( editor ) {
    this.editor = editor;
    this.storage = new CollaborationStorage(editor);
  }

  static get pluginName() {
    return 'RealtimeRevisionHistoryAdapter'
  }

  static get requires() {
    return ['RevisionTracker']
  }

  afterInit() {
    this.elementId = this.editor.sourceElement.dataset.ckeditor5PremiumElementId;
    const revisionHistoryConfig = this.editor.config._config.revisionHistory;

    revisionHistoryConfig.viewerContainer = document.querySelector(`.revision-history-container-data[data-ckeditor5-premium-element-id="${this.elementId}"]`);
    revisionHistoryConfig.viewerEditorElement = revisionHistoryConfig.viewerContainer.querySelector('.revision-viewer-editor');
    revisionHistoryConfig.viewerSidebarContainer = revisionHistoryConfig.viewerContainer.querySelector('.revision-viewer-sidebar');
    revisionHistoryConfig.editorContainer = revisionHistoryConfig.viewerContainer.parentElement.querySelector('.ck-editor-premium-wrapper').parentElement;

    // Initialize plugin.
    const revisionHistoryPlugin = this.editor.plugins.get('RevisionHistory');
    const revisionTrackerPlugin = this.editor.plugins.get('RevisionTracker');

    // Hook to form submit.
    const form = this.editor.sourceElement.closest('form');
    form.addEventListener("submit", (e) => {
      this.updateStorage(revisionHistoryPlugin, revisionTrackerPlugin)
    });

    // Prevent form submission if pointer type is unknown.
    form.addEventListener("click", (e) => {
      console.log(e);
      console.log("open", revisionHistoryPlugin.isRevisionViewerOpen);
      if (e.pointerType.length === 0 && revisionHistoryPlugin.isRevisionViewerOpen) {
        e.preventDefault();
      }
    });

    this.storage.processRevisionDisable();

    this.handleRevisionHistoryActiveClass(revisionHistoryConfig);
  }

  async updateStorage(plugin, tracker) {
    await tracker.update();
    await tracker.saveRevision({name: 'Entity save'});
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

export default RealtimeRevisionHistoryAdapter;
