<?php

namespace Drupal\entity_print_test\Plugin\EntityPrint\PrintEngine;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_print\Plugin\PrintEngineBase;

/**
 * A test print engine plugin.
 *
 * @PrintEngine(
 *   id = "testprintengine",
 *   label = @Translation("Test Print Engine"),
 *   export_type = "pdf"
 * )
 */
class TestPrintEngine extends PrintEngineBase {

  /**
   * The HTML string.
   *
   * @var string
   */
  protected $html;

  /**
   * {@inheritdoc}
   */
  public function send($filename, $force_download = TRUE) {
    echo $filename;
    // Echo the response and then flush, just like a Print implementation would.
    echo 'Using testprintengine - ' . $this->configuration['test_engine_suffix'];
    echo $this->html;
  }

  /**
   * {@inheritdoc}
   */
  public function getBlob() {
    return 'Using testprintengine';
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['test_engine_setting'] = [
      '#title' => $this->t('Test setting'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['test_engine_setting'],
      '#description' => $this->t('Test setting'),
    ];
    $form['test_engine_suffix'] = [
      '#title' => $this->t('Suffix'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['test_engine_suffix'],
      '#description' => $this->t('Suffix'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['test_engine_setting'] = $form_state->getValue(['testprintengine', 'test_engine_setting']);
    $this->configuration['test_engine_suffix'] = $form_state->getValue(['testprintengine', 'test_engine_suffix']);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue(['testprintengine', 'test_engine_setting']) === 'rejected') {
      $form_state->setErrorByName('test_engine_setting', 'Setting has an invalid value');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'test_engine_setting' => '',
      'test_engine_suffix' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getError() {}

  /**
   * {@inheritdoc}
   */
  public function addPage($content) {
    $this->html = $content;
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
