<?php

namespace Drupal\entity_print_test\Plugin\EntityPrint\PrintEngine;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_print\Plugin\PrintEngineBase;

/**
 * The test word print engine.
 *
 * @PrintEngine(
 *   id = "test_word_print_engine",
 *   label = @Translation("Test Word Print Engine"),
 *   export_type = "word_docx"
 * )
 */
class TestWordPrintEngine extends PrintEngineBase {

  /**
   * The HTML string we're building.
   *
   * @var string
   */
  protected $html = '';

  /**
   * {@inheritdoc}
   */
  public function addPage($content) {
    $this->html .= $content;
  }

  /**
   * {@inheritdoc}
   */
  public function send($filename, $force_download = TRUE) {
    echo $filename;
    echo 'Using ' . $this->getPluginId();
    echo $this->html;
  }

  /**
   * {@inheritdoc}
   */
  public function getBlob() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'test_word_setting' => 'my-default',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['test_word_setting'] = [
      '#title' => $this->t('Test Word setting'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['test_word_setting'],
      '#description' => $this->t('Test setting'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['test_word_setting'] = $form_state->getValue(['test_word_print_engine', 'test_word_setting']);
  }

  /**
   * {@inheritdoc}
   */
  public static function dependenciesAvailable() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrintObject() {}

}
