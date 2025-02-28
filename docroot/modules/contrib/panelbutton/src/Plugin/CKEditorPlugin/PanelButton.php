<?php

namespace Drupal\panelbutton\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "panelbutton" plugin.
 *
 * @CKEditorPlugin(
 *   id = "panelbutton",
 *   label = @Translation("CKEditor Panel Button"),
 * )
 */
class PanelButton extends CKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    // @todo Remove backward compatibility conditions when we bump Drupal
    //   requirement to 8.9.0. See https://www.drupal.org/node/3099614
    if (\Drupal::hasService('library.libraries_directory_file_finder')) {
      /** @var \Drupal\Core\Asset\LibrariesDirectoryFileFinder $library_file_finder */
      $path = \Drupal::service('library.libraries_directory_file_finder')->find('panelbutton/plugin.js');
    }
    elseif (\Drupal::moduleHandler()->moduleExists('libraries')) {
      $path = libraries_get_path('panelbutton') . '/plugin.js';
    }
    else {
      $path = 'libraries/panelbutton/plugin.js';
    }
    return $path;
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
  public function getButtons() {
    return [];
  }

}
