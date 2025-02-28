<?php
/**
 * @file
 * Contains \Drupal\ckeditor_uploadimage\Form\CKEditorUploadImageDrupalImageSettings.
 */

namespace Drupal\ckeditor_uploadimage\Form;

use Drupal\ckeditor\Plugin\CKEditorPlugin\DrupalImage;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Entity\Editor;

class CKEditorUploadImageDrupalImage extends DrupalImage {
  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state, Editor $editor) {
    $form = parent::settingsForm($form, $form_state, $editor);
    $imageUpload = $editor->getImageUploadSettings();
    $form['image_upload']['media_entity_image'] = [
      '#type' => 'checkbox',
      '#title' => t(
        'Enable uploaded images that were dropped or pasted from clipboard into the editor save as media entity
        image'
      ),
      '#default_value' => isset($imageUpload['media_entity_image']) ?: FALSE,
    ];
    return $form;
  }
}
