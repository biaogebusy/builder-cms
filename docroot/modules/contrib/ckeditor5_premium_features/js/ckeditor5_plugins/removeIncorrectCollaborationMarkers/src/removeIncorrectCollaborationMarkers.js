/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

class RemoveIncorrectCollaborationMarkers {
  constructor( editor ) {
    this.editor = editor;
  }

  afterInit() {
    // Subscribes to the `change:data` event to recognize markers in the document,
    // then checks if the suggestion/comment data for the marker is available. If not - removes the marker.
    this.editor.model.document.once( 'change:data', () => {
      const isTrackChangesEnabled = this.editor.plugins.has( 'TrackChanges' );
      const isCommentsEnabled = this.editor.plugins.has( 'CommentsRepository' );

      const markers = Array.from( this.editor.model.document.differ.getChangedMarkers() );
      const suggestionMarkers = markers.filter( marker => marker.name.includes( 'suggestion' ) );
      const commentMarkers = markers.filter( marker => marker.name.includes( 'comment' ) );

      if ( isTrackChangesEnabled ) {
        const trackChanges = this.editor.plugins.get( 'TrackChanges' );

        // Check if the marker has a corresponding suggestion data. If not - remove the marker.
        for ( const marker of suggestionMarkers ) {
          const { name } = marker;
          const parts = name.split( ':' );
          const suggestionId = parts.length < 5 ? parts[ 2 ] : parts[ 3 ];

          const hasSuggestion = trackChanges.getSuggestions().some( suggestion => suggestion.id == suggestionId );

          if ( !hasSuggestion ) {
            this._removeSuggestionMarker( suggestionId );
          }
        }
      }

      if ( isCommentsEnabled ) {
        const commentsRepository = this.editor.plugins.get( 'CommentsRepository' );
        // Check if the marker has a corresponding comment data. If not - remove the marker.
        for ( const marker of commentMarkers ) {
          const { name } = marker;
          const commentId = name.split( ':' )[ 1 ];

          const hasComment = commentsRepository.getCommentThreads().some( comment => comment.id == commentId );

          if ( !hasComment ) {
            this._removeCommentMarker( commentId );
          }
        }
      }
    }, { priority: 'high' } )
  }

  /*
   * Removes the suggestion marker from the document.
   */
  _removeSuggestionMarker( id ) {
    const markers = this.editor.model.markers.getMarkersGroup( 'suggestion' );

    const suggestionMarker = Array.from( markers ).filter( marker => marker.name.includes( id ) );

    this.editor.model.change( writer => {
      writer.removeMarker( ...suggestionMarker );
    }, { priority: 'high' } );
  }

  /*
   * Removes the comment marker from the document.
   */
  _removeCommentMarker( id ) {
    const markers = this.editor.model.markers.getMarkersGroup( 'comment' );

    const commentMarker = Array.from( markers ).filter( marker => marker.name.includes( id ) );

    this.editor.model.change( writer => {
      writer.removeMarker( ...commentMarker );
    } );
  }
}

export default RemoveIncorrectCollaborationMarkers;
