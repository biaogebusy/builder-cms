<?php

namespace Drupal\autogrow\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\ckeditor\CKEditorPluginConfigurableInterface;
use Drupal\ckeditor\CKEditorPluginContextualInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "autogrow" plugin.
 *
 * @CKEditorPlugin(
 *   id = "autogrow",
 *   label = @Translation("Autogrow")
 * )
 */
class Autogrow extends CKEditorPluginBase implements CKEditorPluginConfigurableInterface, CKEditorPluginContextualInterface {

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(Editor $editor) {
    $settings = $editor->getSettings();
    return isset($settings['plugins']['autogrow']) ? $settings['plugins']['autogrow']['enable'] : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return 'libraries/autogrow/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    $settings = $editor->getSettings();

    return [
      'autoGrow_minHeight' => !empty($settings['plugins']['autogrow']['min_height']) ? $settings['plugins']['autogrow']['min_height'] : 250,
      'autoGrow_maxHeight' => !empty($settings['plugins']['autogrow']['max_height']) ? $settings['plugins']['autogrow']['max_height'] : 600,
      'autoGrow_bottomSpace' => !empty($settings['plugins']['autogrow']['bottom_space']) ? $settings['plugins']['autogrow']['bottom_space'] : 50,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state, Editor $editor) {
    $settings = $editor->getSettings();

    $form['enable'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Autogrow'),
      '#default_value' => !empty($settings['plugins']['autogrow']['enable']) ? $settings['plugins']['autogrow']['enable'] : false,
    );

    $form['min_height'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Minimum height'),
      '#description' => $this->t('Defines the minimum height that the editor will always assume, no matter how much content it includes.'),
      '#default_value' => !empty($settings['plugins']['autogrow']['min_height']) ? $settings['plugins']['autogrow']['min_height'] : 250,
    );

    $form['max_height'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Maximum height'),
      '#description' => $this->t('Set this in order to prevent the situation where huge amounts of content will cause the editor to expand infinitely.'),
      '#default_value' => !empty($settings['plugins']['autogrow']['max_height']) ? $settings['plugins']['autogrow']['max_height'] : 600,
    );

    $form['bottom_space'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Bottom space'),
      '#description' => $this->t('This option lets you insert some extra space that will always be added between the content and the editor bottom bar. For example, you can set it to 50 pixels in order to prevent the editor from looking too cramped.'),
      '#default_value' => !empty($settings['plugins']['autogrow']['bottom_space']) ? $settings['plugins']['autogrow']['bottom_space'] : 50,
    );

    return $form;
  }

}
