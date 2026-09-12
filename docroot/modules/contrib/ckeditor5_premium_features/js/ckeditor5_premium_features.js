/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

/**
 * @file
 * CKEditor 5 premium features override of CKEditor 5 implementation
 * of {@link Drupal.editors} API.
 *
 * Prevents executing original detach on tabledrag and ensures there is no
 * editor already initialized before attaching new one.
 */

((Drupal) => {

  Drupal.editors.ckeditor5.coreAttach = Drupal.editors.ckeditor5.attach;
  Drupal.editors.ckeditor5.coreDetach = Drupal.editors.ckeditor5.detach;

  Drupal.editors.ckeditor5.attach = function(element, format) {
    const id = element.getAttribute('data-ckeditor5-id');
    const editor = Drupal.CKEditor5Instances.get(id);
    // Init new editor only if we don't yet have it on element.
    if (!editor) {
      Drupal.editors.ckeditor5.coreAttach(element, format);
    }
  }

  Drupal.editors.ckeditor5.detach = function(element, format, trigger) {
    if (trigger !== 'move') {
      Drupal.editors.ckeditor5.coreDetach(element, format, trigger);
    }
  }

})(Drupal);
