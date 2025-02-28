<?php
/**
 * @file
 * Contains \Drupal\ckeditor_uploadimage\Plugin\CKEditorPlugin\UploadImage.
 */

namespace Drupal\ckeditor_uploadimage\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginInterface;
use Drupal\Component\Plugin\PluginBase;
use Drupal\editor\Entity\Editor;
use Drupal\Core\Url;

/**
 * Defines the "templates" plugin.
 *
 * @CKEditorPlugin(
 *   id = "uploadimage",
 *   label = @Translation("CKEditor Upload Image"),
 *   module = "ckeditor_uploadimage"
 * )
 */
class UploadImage extends PluginBase implements CKEditorPluginInterface {
  /**
   * {@inheritdoc}
   */
  function getDependencies(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  function getFile() {
    $path = 'libraries/ckeditor/plugins/' . $this->getPluginId() . '/plugin.js';
    if (file_exists('profiles/' . \Drupal::installProfile() . "/$path")) {
      return 'profiles/' . \Drupal::installProfile() . "/$path";
    }
    return $path;
  }

  /**
   * {@inheritdoc}
   */
  function isInternal() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    $filterFormatId = $editor->getFilterFormat()->id();
    return [
      'imageUploadUrl' => Url::fromRoute('ckeditor_uploadimage.save', ['filterFormatId' => $filterFormatId])->toString(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  function getLibraries(Editor $editor) {
    return [];
  }
}
