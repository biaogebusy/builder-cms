<?php
/**
 * @file
 * Contains \Drupal\ckeditor_uploadimage\Plugin\CKEditorPlugin\Notification.
 */

namespace Drupal\ckeditor_uploadimage\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginInterface;
use Drupal\Component\Plugin\PluginBase;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "templates" plugin.
 *
 * @CKEditorPlugin(
 *   id = "notification",
 *   label = @Translation("CKEditor Notification"),
 *   module = "ckeditor_uploadimage"
 * )
 */
class Notification extends PluginBase implements CKEditorPluginInterface {
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
    return [];
  }

  /**
   * {@inheritdoc}
   */
  function getLibraries(Editor $editor) {
    return [];
  }
}
