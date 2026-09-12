/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

class DisableCollaborationMarkersInCaption {

  constructor( editor ) {
    this.editor = editor;
  }

  afterInit() {
    const editor = this.editor;
    const commentCommand = editor.commands.get( 'addCommentThread' );
    const trackChangesCommand = editor.commands.get( 'trackChanges' );

    editor.set( 'disabledCommands', false );

    const hasCommentsRepository = editor.plugins.has('CommentsRepository');
    const hasTrackChangesEditing = editor.plugins.has('TrackChangesEditing');
    const hasDrupalImage = editor.plugins.has('DrupalImage');

    if ((!hasCommentsRepository && !hasTrackChangesEditing) || !hasDrupalImage) {
      return;
    }

    if (hasTrackChangesEditing) {
      const tcEditing = editor.plugins.get( 'TrackChangesEditing' );
      if (!this.editor.commands.get('toggleImageCaption')) {
        try {
          tcEditing.enableCommand( 'toggleImageCaption', ( executeCommand, options ) => {
            executeCommand( options );
          }, { priority: 'high' } );
        } catch (error) {
          return;
        }
      }
    }

    if (trackChangesCommand) {
      const toggleImageCaptionCommand = this.editor.commands.get('toggleImageCaption');
      trackChangesCommand.on('change:value', (evt, data, value) => {
        if (value) {
          toggleImageCaptionCommand.forceDisabled('drupal-premium-features')
        } else {
          toggleImageCaptionCommand.clearForceDisabled('drupal-premium-features')
        }
      })
    }

    let tcOriginalValue;

    editor.model.document.on( 'change', () => {
      if ( !editor.disabledCommands && trackChangesCommand) {
        tcOriginalValue = trackChangesCommand.value;
      }
    }, { priority: 'highest' } );

    editor.model.document.on( 'change', () => {
      const range = editor.model.document.selection.getFirstRange();
      const ancestor = range.getCommonAncestor();
      if ( ancestor.name === 'caption' ) {
        if (commentCommand) {
          commentCommand.forceDisabled( 'drupal-premium-features' );
        }
        if (trackChangesCommand) {
          trackChangesCommand.value = false;
          trackChangesCommand.forceDisabled( 'drupal-premium-features' );
        }
        editor.set( 'disabledCommands', true );
      } else {
        if ( editor.disabledCommands ) {
          if (commentCommand) {
            commentCommand.clearForceDisabled( 'drupal-premium-features' );
          }
          if (trackChangesCommand) {
            trackChangesCommand.clearForceDisabled('drupal-premium-features');
          }
          editor.set( 'disabledCommands', false );
          if ( tcOriginalValue && trackChangesCommand) {
            trackChangesCommand.value = true;
          }
        }
      }
    }, { priority: 'low' } );
  }
}

export default DisableCollaborationMarkersInCaption;
