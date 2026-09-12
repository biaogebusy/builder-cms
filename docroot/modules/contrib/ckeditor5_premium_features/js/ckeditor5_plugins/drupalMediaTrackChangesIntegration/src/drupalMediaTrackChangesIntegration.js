/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

import { Plugin } from 'ckeditor5/src/core';

class DrupalMediaTrackChangesIntegration extends Plugin {

  static get pluginName() {
    return 'DrupalMediaTrackChangesIntegration';
  }

  afterInit() {
    const editor = this.editor;

    if (!editor.plugins.has( 'TrackChangesEditing' )) {
      return;
    }

    const trackChangesEditing = editor.plugins.get( 'TrackChangesEditing' );

    trackChangesEditing.enableCommand( 'insertDrupalMedia' );

    const t = editor.t;
    const descriptionFactory = typeof trackChangesEditing._descriptionFactory === 'undefined' ? trackChangesEditing.descriptionFactory : trackChangesEditing._descriptionFactory;
    descriptionFactory.registerElementLabel(
      'drupalMedia',

      quantity => t( {
        string: 'drupal media',
        plural: '%0 drupal medias',
        id: 'ELEMENT_DRUPAL_MEDIA'
      }, quantity )
    );
  }
}

export default DrupalMediaTrackChangesIntegration;
