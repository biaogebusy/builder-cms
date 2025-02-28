<?php

namespace Drupal\ckeditor_textindent\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\ckeditor\CKEditorPluginConfigurableInterface;
use Drupal\ckeditor\CKEditorPluginContextualInterface;
use Drupal\ckeditor\CKEditorPluginCssInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\Entity\Editor;

/**
 * @CKEditorPlugin(
 *   id = "textindent",
 *   label = @Translation("Text Indent")
 * )
 */
class TextIndent extends CKEditorPluginBase implements CKEditorPluginContextualInterface, CKEditorPluginConfigurableInterface, CKEditorPluginCssInterface {

  /**
   * {@inheritdoc}
   */
  public function getCssFiles(Editor $editor) {
    return [
    ];
  }

  /**
   * {@inheritdoc}
   *
   * NOTE: The keys of the returned array corresponds to the CKEditor button
   * names. They are the first argument of the editor.ui.addButton() or
   * editor.ui.addRichCombo() functions in the plugin.js file.
   */
  public function getButtons() {
    return [
      'textindent' => [
        'label' => $this->t('Text Indent'),
        'image' => drupal_get_path('module', 'ckeditor_textindent') . '/js/plugins/textindent/icons/textindent.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return drupal_get_path('module', 'ckeditor_textindent') . '/js/plugins/textindent/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(Editor $editor) {
    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    $settings = $editor->getSettings();
    $config = ['indentation' => '2em'];
    if (isset($settings['plugins']['textindent'])) {
      $config = $settings['plugins']['textindent'];
    }
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state, Editor $editor) {
    $settings = $editor->getSettings();
    $config = ['indentation' => '2em'];
    if (isset($settings['plugins']['textindent'])) {
      $config = $settings['plugins']['textindent'];
    }

    $form['indentation'] = [
      '#type' => 'textfield',
      '#title' => 'Indentation',
      '#default_value' =>  $config['indentation']
    ];

    return $form;
  }

}
