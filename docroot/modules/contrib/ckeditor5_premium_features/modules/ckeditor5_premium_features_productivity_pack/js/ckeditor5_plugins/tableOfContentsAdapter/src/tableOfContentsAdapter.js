/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

import { Plugin } from 'ckeditor5/src/core';
export default class TableOfContentsAdapter extends Plugin {
  static get pluginName() {
    return 'TableOfContentsAdapter'
  }

  afterInit() {
    const editor = this.editor;
    const conversion = editor.conversion;

    conversion.for( 'dataDowncast' ).add( dispatcher => {
      dispatcher.on( 'insert:tableOfContents', ( evt, data, { writer, mapper } ) => {
        const modelElement = data.item;
        const viewElement = mapper.toViewElement( modelElement );

        if (viewElement) {
          writer.addClass( 'ck-table-of-contents', viewElement );
        }

      } )
    } );
  }
}
