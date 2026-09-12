<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_import_word\Controller;

use Drupal\ckeditor5\Controller\CKEditor5ImageController;
use Drupal\Component\Utility\NestedArray;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * {@inheritDoc}
 */
class CKEditor5ImportWordImageUploadController extends CKEditor5ImageController {

  /**
   * {@inheritDoc}
   */
  public function upload(Request $request): Response {
    $editor = $request->get('editor');
    if ($editor) {
      $settings = $editor->getSettings();
      $toolbar = NestedArray::getValue($settings, [
        'toolbar',
        'items',
      ]);

      // While importing a Word file with multiple images, all of them will be uploaded simultaneously with the same file name.
      // That will cause an upload error, this code will add extra hash to the file name and prevent this issue.
      if (is_array($toolbar) && in_array('importWord', $toolbar)) {
        $upload = $request->files->get('upload');
        $filename = bin2hex(random_bytes(5)) . '_' . $upload->getClientOriginalName();
        $newUploadedFile = new UploadedFile($upload->getRealPath(), $filename, $upload->getMimeType(), $upload->getError(), FALSE);
        $request->files->set('upload', $newUploadedFile);
      }
    }

    return parent::upload($request);
  }

}
