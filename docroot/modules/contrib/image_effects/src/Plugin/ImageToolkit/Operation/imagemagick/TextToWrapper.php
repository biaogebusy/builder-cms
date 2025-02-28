<?php

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\Core\File\FileSystemInterface;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\TextToWrapperTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines Imagemagick Text Overlay text-to-wrapper operation.
 *
 * @ImageToolkitOperation(
 *   id = "image_effects_imagemagick_text_to_wrapper",
 *   toolkit = "imagemagick",
 *   operation = "text_to_wrapper",
 *   label = @Translation("Overlays text over an image"),
 *   description = @Translation("Overlays text over an image.")
 * )
 */
class TextToWrapper extends ImagemagickImageToolkitOperationBase {

  use TextToWrapperTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    // Get a temporary wrapper image object via the GD toolkit.
    $gd_wrapper = \Drupal::service('image.factory')->get(NULL, 'gd');
    $gd_wrapper->apply('text_to_wrapper', $arguments);
    // Flush the temporary wrapper to disk, reopen via ImageMagick and return.
    if ($gd_wrapper) {
      $file_system = \Drupal::service('file_system');
      // Temporary file prefix is limited to 3 chars for Windows compatibility.
      $tmp_file = $file_system->tempnam('temporary://', 'ifx');
      $gd_wrapper_destination = $tmp_file . '.png';
      $file_system->move($tmp_file, $gd_wrapper_destination, FileSystemInterface::CREATE_DIRECTORY);
      $gd_wrapper->save($gd_wrapper_destination);
      $tmp_wrapper = \Drupal::service('image.factory')->get($gd_wrapper_destination, 'imagemagick');
      // Defer removal of the temporary file to after it has been processed.
      drupal_register_shutdown_function([static::class, 'deleteTempFile'], $gd_wrapper_destination);
      return $this->getToolkit()->apply('replace_image', ['replacement_image' => $tmp_wrapper]);
    }
    return FALSE;
  }

  /**
   * Delete the image effect temporary file after it has been used.
   *
   * @param string $file_path
   *   Path of the file that is about to be deleted.
   */
  public static function deleteTempFile($file_path) {
    if (file_exists($file_path)) {
      \Drupal::service('file_system')->delete($file_path);
    }
  }

}
