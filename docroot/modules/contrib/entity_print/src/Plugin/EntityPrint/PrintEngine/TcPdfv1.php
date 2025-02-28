<?php

namespace Drupal\entity_print\Plugin\EntityPrint\PrintEngine;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_print\Plugin\ExportTypeInterface;
use Drupal\entity_print\Plugin\PrintEngineBase;

/**
 * TCPDF plugin implementation.
 *
 * @PrintEngine(
 *   id = "tcpdfv1",
 *   label = @Translation("TCPDF (v1)"),
 *   export_type = "pdf"
 * )
 *
 * To use this implementation you will need the TCPDF library, simply run
 *
 * @code
 *     composer require "tecnickcom/tcpdf ~6"
 * @endcode
 */
class TcPdfv1 extends PrintEngineBase {

  /**
   * The TCPDF implementation.
   *
   * @var \TCPDF
   */
  protected $tcpdf;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ExportTypeInterface $export_type) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $export_type);
    $this->tcpdf = new \TCPDF();
  }

  /**
   * {@inheritdoc}
   */
  public static function getInstallationInstructions() {
    return t('Please install with: @command', ['@command' => 'composer require "tecnickcom/tcpdf ~6"']);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'default_paper_size' => 'A4',
      'default_paper_orientation' => static::PORTRAIT,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $page_formats = array_combine(array_keys(\TCPDF_STATIC::$page_formats), array_keys(\TCPDF_STATIC::$page_formats));
    $form['default_paper_size'] = [
      '#title' => $this->t('Paper Size'),
      '#type' => 'select',
      '#options' => $page_formats,
      '#default_value' => $this->configuration['default_paper_size'],
      '#description' => $this->t('The page size to print the PDF to.'),
    ];
    $form['default_paper_orientation'] = [
      '#title' => $this->t('Paper Orientation'),
      '#type' => 'select',
      '#options' => [
        static::PORTRAIT => $this->t('Portrait'),
        static::LANDSCAPE => $this->t('Landscape'),
      ],
      '#default_value' => $this->configuration['default_paper_orientation'],
      '#description' => $this->t('The paper orientation one of Landscape or Portrait'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function addPage($content) {
    $this->tcpdf->AddPage($this->configuration['default_paper_orientation'], $this->configuration['default_paper_size']);
    $this->tcpdf->writeHTML($content);
  }

  /**
   * {@inheritdoc}
   */
  public function send($filename, $force_download = TRUE) {
    // If we have a filename then we force the download otherwise we open in the
    // browser.
    $this->tcpdf->Output($filename, $force_download ? 'D' : 'I');
  }

  /**
   * {@inheritdoc}
   */
  public function getBlob() {
    return $this->tcpdf->Output('', 'S');
  }

  /**
   * {@inheritdoc}
   */
  public static function dependenciesAvailable() {
    return class_exists('\TCPDF') && !drupal_valid_test_ua();
  }

  /**
   * {@inheritdoc}
   */
  public function getPrintObject() {
    return $this->tcpdf;
  }

}
