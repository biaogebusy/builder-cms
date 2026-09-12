<?php

namespace Drupal\xls_serialization\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\xls_serialization\XlsSerializationConstants;

/**
 * Provides functionality related to styling mechanisms for Excel exports.
 */
trait ExcelExportStyleTrait {

  /**
   * Initializes the serializer formats for the class.
   */
  protected function initializeSerializerFormats() {
    $this->formats = ['xls', 'xlsx'];
    $this->formatProviders =
      ['xls' => 'xls_serialization', 'xlsx' => 'xls_serialization'];
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();

    $options['xls_settings']['contains'] = [
      'xls_format' => ['default' => XlsSerializationConstants::EXCEL_2007_FORMAT],
      'strip_tags' => ['default' => TRUE],
      'trim' => ['default' => TRUE],
    ];

    $options['xls_settings']['metadata']['contains'] = [
      // The 'created' and 'modified' elements are not exposed here, as they
      // default to the current time (that the spreadsheet is created), and
      // would probably just confuse the UI.
      'creator' => ['default' => ''],
      'last_modified_by' => ['default' => ''],
      'title' => ['default' => ''],
      'description' => ['default' => ''],
      'subject' => ['default' => ''],
      'keywords' => ['default' => ''],
      'category' => ['default' => ''],
      'manager' => ['default' => ''],
      'company' => ['default' => ''],
      // @todo Expose a UI for custom properties.
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    switch ($form_state->get('section')) {
      case 'style_options':
        // Change format to radios instead, since multiple formats here do not
        // make sense as they do for REST exports.
        $form['formats']['#type'] = 'radios';
        $form['formats']['#default_value'] = reset($this->options['formats']);

        // Remove now confusing description.
        unset($form['formats']['#description']);

        // XLS options.
        $xls_options = $this->options['xls_settings'];
        $form['xls_settings'] = [
          '#type' => 'details',
          '#open' => TRUE,
          '#title' => $this->t('XLS(X) settings'),
          '#tree' => TRUE,
          'xls_format' => [
            '#type' => 'select',
            '#title' => $this->t('Format'),
            '#options' => [
              // @todo Add all PHPExcel supported formats.
              XlsSerializationConstants::EXCEL_2007_FORMAT => $this->t('Excel 2007'),
              XlsSerializationConstants::EXCEL_5_FORMAT => $this->t('Excel 5'),
            ],
            '#default_value' => $xls_options['xls_format'],
          ],
          'strip_tags' => [
            '#type' => 'checkbox',
            '#title' => $this->t('Strip HTML'),
            '#description' => $this->t('Strips HTML tags from CSV cell values.'),
            '#default_value' => $xls_options['strip_tags'],
          ],
          'trim' => [
            '#type' => 'checkbox',
            '#title' => $this->t('Trim whitespace'),
            '#description' => $this->t('Trims whitespace from beginning and end of CSV cell values.'),
            '#default_value' => $xls_options['trim'],
          ],
        ];

        $metadata = !empty($xls_options['metadata']) ? array_filter($xls_options['metadata']) : [];

        // XLS metadata.
        $form['xls_settings']['metadata'] = [
          '#type' => 'details',
          '#title' => $this->t('Document metadata'),
          '#open' => $metadata,
        ];

        $xls_fields = [
          'creator' => $this->t('Author/creator name'),
          'last_modified_by' => $this->t('Last modified by'),
          'title' => $this->t('Title'),
          'description' => $this->t('Description'),
          'subject' => $this->t('Subject'),
          'keywords' => $this->t('Keywords'),
          'category' => $this->t('Category'),
          'manager' => $this->t('Manager'),
          'company' => $this->t('Company'),
        ];

        foreach ($xls_fields as $xls_field_key => $xls_field_title) {
          $form['xls_settings']['metadata'][$xls_field_key] = [
            '#type' => 'textfield',
            '#title' => $xls_field_title,
          ];

          if (isset($xls_options['metadata'][$xls_field_key])) {
            $form['xls_settings']['metadata'][$xls_field_key]['#default_value'] = $xls_options['metadata'][$xls_field_key];
          }
        }
        break;
    }
  }

}
