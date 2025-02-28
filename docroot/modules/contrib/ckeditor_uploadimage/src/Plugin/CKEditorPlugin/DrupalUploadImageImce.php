<?php
/**
 * @file
 * Contains \Drupal\ckeditor_uploadimage\Plugin\CKEditorPlugin\DrupalUploadImageImce.
 */

namespace Drupal\ckeditor_uploadimage\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginInterface;
use Drupal\ckeditor\CKEditorPluginContextualInterface;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\Bytes;
use Drupal\editor\Entity\Editor;
use Drupal\Core\Url;
use Drupal\Component\Utility\Environment;

/**
 * Defines the "templates" plugin.
 *
 * @CKEditorPlugin(
 *   id = "drupaluploadimageimce",
 *   label = @Translation("CKEditor Drupal Upload Image IMCE"),
 *   module = "ckeditor_uploadimage"
 * )
 */
class DrupalUploadImageImce extends PluginBase implements CKEditorPluginInterface, CKEditorPluginContextualInterface {
  /**
   * {@inheritdoc}
   */
  function getDependencies(Editor $editor) {
    return [
      'uploadimage',
      'uploadwidget',
      'filetools',
      'notificationaggregator',
      'notification',
    ];
  }

  /**
   * {@inheritdoc}
   */
  function getFile() {
    return drupal_get_path('module', 'ckeditor_uploadimage') . '/js/plugins/drupaluploadimage/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  function isEnabled(Editor $editor) {
    $has_access = \Drupal::currentUser()->hasPermission('use ckeditor_uploadimage');
    if (!$editor->hasAssociatedFilterFormat() || !$has_access) {
      return FALSE;
    }

    // Automatically enable this plugin if the text format associated with this
    // text editor uses the filter_align or filter_caption filter and the
    // ImceImage button is enabled.
    $format = $editor->getFilterFormat();
    if ($format->filters('filter_align')->status || $format->filters('filter_caption')->status) {
      $enabled = FALSE;
      $settings = $editor->getSettings();
      foreach ($settings['toolbar']['rows'] as $row) {
        foreach ($row as $group) {
          foreach ($group['items'] as $button) {
            if ($button === 'ImceImage') {
              $enabled = TRUE;
            }
          }
        }
      }
      return $enabled;
    }

    return FALSE;
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
    $editorObj = editor_load($filterFormatId);
    $imageUploadSettings = $editorObj->getImageUploadSettings();
    $maxFilesize = min(Bytes::toInt($imageUploadSettings['max_size']), Environment::getUploadMaxSize());
    return [
      'maxImageFilesize' => $maxFilesize,
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
