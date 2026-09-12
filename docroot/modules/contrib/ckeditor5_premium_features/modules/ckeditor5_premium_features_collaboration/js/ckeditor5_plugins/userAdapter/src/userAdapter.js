/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

class UserAdapter {
  constructor( editor ) {
    this.editor = editor;
  }

  static get pluginName() {
    return 'UserAdapter'
  }

  init() {
    if (typeof this.editor.sourceElement === "undefined") {
      return;
    }

    if (typeof drupalSettings.ckeditor5Premium === "undefined" || !this.editor.plugins.has('Users') ) {
      return;
    }

    const usersPlugin = this.editor.plugins.get( 'Users' );
    const users = drupalSettings.ckeditor5Premium.users;
    for (const user in users) {
      if (usersPlugin.users.get(user)) {
        continue;
      }
      usersPlugin.addUser(users[user]);
    }

    // Set the current user.
    if (!usersPlugin.me) {
      usersPlugin.defineMe( drupalSettings.user.uid );
    }

    this.editor.on('ready', () => {
      this.setPermissions();
    })
  }

  setPermissions() {
    let textFormat = this.editor.sourceElement.getAttribute('data-editor-active-text-format');
    let permissionsPlugin = this.editor.plugins.get('Permissions');
    let permissions = drupalSettings.ckeditor5Premium?.current_user?.editor_permission[textFormat];
    if (!permissions) {
      return;
    }
    const isTrackChangesEnabled = this.editor.plugins.has('TrackChanges');

    if (typeof permissions !== 'object') {
      this.editor.enableReadOnlyMode(this.editor.id);
    } else {
      let documentAdmin = permissions.indexOf('document:admin');
      permissionsPlugin.setPermissions(permissions);
      if (permissions.length === 1 && permissions[0] === 'comment:write') {
        this.disablePlugins('commentOnly');
      }
      if (permissions.includes('document:write') && documentAdmin === -1) {
        if (isTrackChangesEnabled) {
          this.editor.execute('trackChanges');
          this.editor.commands.get('acceptSuggestion').forceDisabled('suggestionOnly');
          this.editor.commands.get('acceptAllSuggestions').forceDisabled('suggestionOnly');
          this.editor.commands.get('discardAllSuggestions').forceDisabled('suggestionOnly');
          this.editor.commands.get('discardSuggestion').forceDisabled('suggestionOnly');
          this.editor.commands.get('trackChanges').forceDisabled('suggestionOnly');
          this.disablePlugins('suggestionOnly');
        } else {
          this.editor.enableReadOnlyMode(this.editor.id);
        }
      }

    }
  }

  disablePlugins(id) {
    const plugins = this.editor.plugins;
    if (plugins.has('SourceEditing')) {
      plugins.get('SourceEditing').forceDisabled(id);
    }
    if (plugins.has('SourceEditingAdvanced')) {
      plugins.get('SourceEditingAdvanced').forceDisabled(id);
    }
    if (plugins.has('RevisionTracker')) {
      plugins.get('RevisionTracker').forceDisabled(id);
    }
    if (plugins.has('RevisionHistory')) {
      plugins.get('RevisionHistory').forceDisabled(id);
    }
    if (plugins.has('TrackChanges')) {
      plugins.get('TrackChanges').forceDisabled(id);
    }
  }

}

export default UserAdapter;
