<?php

namespace Drupal\Tests\dropzonejs\FunctionalJavascript;

use Drupal\file\Entity\File;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Base class for DropzoneJS Web driver functional test base.
 *
 * @package Drupal\Tests\entity_browser\FunctionalJavascript
 */
abstract class DropzoneJsWebDriverTestBase extends WebDriverTestBase {

  protected $defaultTheme = 'starterkit_theme';

  /**
   * Simple grey rectangle image data.
   *
   * @var string
   */
  var $fileData = "iVBORw0KGgoAAAANSUhEUgAAAGQAAABkCAAAAABVicqIAAAACXBIWXMAAAsTAAALEwEAmpwYAAAA
B3RJTUUH3gIYBAEMHCkuWQAAAB1pVFh0Q29tbWVudAAAAAAAQ3JlYXRlZCB3aXRoIEdJTVBkLmUH
AAAAQElEQVRo3u3NQQ0AAAgEoNN29i9kCh9uUICa3OtIJBKJRCKRSCQSiUQikUgkEolEIpFIJBKJ
RCKRSCQSiUTyPlnSFQER9VCp/AAAAABJRU5ErkJggg==";

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'dropzonejs_test',
    'views',
    'block',
    'node',
    'file',
    'image',
    'field_ui',
    'views_ui',
    'system',
  ];

  /**
   * Creates an file.
   *
   * @param string $name
   *   The name of the image.
   * @param string $extension
   *   File extension.
   *
   * @return \Drupal\file\FileInterface
   *   Returns an image.
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function getFile($name, $extension = 'jpg') {
    file_put_contents('public://' . $name . '.' . $extension, $this->fileData);

    $file = File::create([
      'filename' => $name . '.' . $extension,
      'uri' => 'public://' . $name . '.' . $extension,
    ]);
    $file->setPermanent();
    $file->save();

    return $file;
  }

  /**
   * Waits for jQuery to become ready and animations to complete.
   */
  protected function waitForAjaxToFinish() {
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

  /**
   * Drop a predefined file to dropzone.
   *
   * @param string $filename
   *   (optional) File name.
   * @param string $extension
   *   (optional) File extension.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function dropFile($filename = 'notalama', $extension = 'jpg') {
    // Sometimes we are not yet switched to the iframe. Stop the horses a bit
    // here.
    sleep(1);
    $file = $this->getFile($filename, $extension);

    $input = <<<JS
      jQuery('.form-type-dropzonejs').append('<input type=\'file\' name=\'fakefile\'>');
JS;
    $this->getSession()->evaluateScript($input);

    $full_path = \Drupal::service('file_system')->realPath($file->getFileUri());
    if (is_file($full_path)) {
      $path = $full_path;
    }

    if (!isset($path) || !file_exists($path)) {
      throw new \RuntimeException("File $path does not exist");
    }

    $this->getSession()->getPage()->attachFileToField('fakefile', $path);

    $drop = <<<JS
    (function(jQuery) {
      var fakeFileInputs = jQuery('input[name=fakefile]' );
      var fileList = fakeFileInputs.map(function (i, el) { return el.files[0] });
      var e = jQuery.Event('drop', { dataTransfer : { files : fileList } });
      jQuery('.dropzone' )[0].dropzone.listeners[0].events.drop(e);
      fakeFileInputs.map(function (i, el) { return el.remove(); });
    })(jQuery);
JS;

    $this->getSession()->evaluateScript($drop);
  }

}
