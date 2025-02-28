<?php

namespace Drupal\xls_serialization\Plugin\views\display;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rest\Plugin\views\display\RestExport;

/**
 * Provides an Excel export display plugin.
 *
 * This overrides the REST Export display to make labeling clearer on the admin
 * UI, and add specific Excel-related functionality.
 *
 * @ingroup views_display_plugins
 *
 * @ViewsDisplay(
 *   id = "excel_export",
 *   title = @Translation("Excel export"),
 *   help = @Translation("Export the view results to an Excel file."),
 *   uses_route = TRUE,
 *   admin = @Translation("Excel export"),
 *   returns_response = TRUE
 * )
 */
class ExcelExport extends RestExport {

  /**
   * Overrides the content type of the data response, if needed.
   *
   * @var string
   */
  protected $contentType = 'xlsx';

  /**
   * {@inheritdoc}
   */
  public static function buildResponse($view_id, $display_id, array $args = []) {
    // Do not call the parent method, as it makes the response harder to alter.
    // @see https://www.drupal.org/node/2779807
    $build = static::buildBasicRenderable($view_id, $display_id, $args);

    // Setup an empty response, so for example, the Content-Disposition header
    // can be set.
    $response = new CacheableResponse('', 200);
    $build['#response'] = $response;

    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');

    $output = (string) $renderer->renderRoot($build);

    $response->setContent($output);
    $cache_metadata = CacheableMetadata::createFromRenderArray($build);
    $response->addCacheableDependency($cache_metadata);

    $response->headers->set('Content-type', $build['#content_type']);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    // Add the content disposition header if a custom filename has been used.
    if (($response = $this->view->getResponse()) && $this->getOption('filename')) {
      $response->headers->set('Content-Disposition', 'attachment; filename="' . $this->generateFilename($this->getOption('filename')) . '"');
    }

    return parent::render();
  }

  /**
   * Given a filename and a view, generate a filename.
   *
   * @param string $filename_pattern
   *   The filename, which may contain replacement tokens.
   *
   * @return string
   *   The filename with any tokens replaced.
   */
  protected function generateFilename($filename_pattern) {
    return $this->globalTokenReplace($filename_pattern);
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['displays'] = ['default' => []];

    // Set the default style plugin, and default to fields.
    $options['style']['contains']['type']['default'] = 'excel_export';
    $options['row']['contains']['type']['default'] = 'data_field';

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);

    // Excel sheet header formatting.
    $header_bold = $this->getOption('header_bold') ? $this->t('Bold') : $this->t('Non-Bold');
    $header_italic = $this->getOption('header_italic') ? $this->t('Italic') : $this->t('Non-Italic');
    $header_background_color = $this->getOption('header_background_color') ? $this->t('RGB code background color: @rgb_code', ['@rgb_code' => $this->getOption('header_background_color')]) : $this->t('No background color (white)');
    $format_header_value = implode(', ', [
      $header_bold,
      $header_italic,
      $header_background_color,
    ]);

    // Add category for Excel sheet header formatting.
    $categories['excel_sheet_header'] = [
      'title' => $this->t('Excel sheet header'),
      'column' => 'second',
      'build' => [
        '#weight' => -1,
      ],
    ];

    // Add option for Excel sheet header formatting.
    $options['format_header'] = [
      'category' => 'excel_sheet_header',
      'title' => $this->t('Format header'),
      'value' => $format_header_value,
    ];

    // Excel conditional formatting.
    $field_names = $this->view->getDisplay()->getFieldLabels();
    $operator_options = [0 => '=', 1 => '<>'];

    for ($i = 0; $i <= 4; $i++) {
      $current_conditional_formatting_base_field = $this->getOption('conditional_formatting_base_field_' . $i);
      if ($current_conditional_formatting_base_field === 'Select a field' || $current_conditional_formatting_base_field === '' || $current_conditional_formatting_base_field === NULL) {
        $conditional_formatting_value[$i] = 'None';
      }
      else {
        $conditional_formatting_base_field[$i] = $this->t('If Field @base_field', ['@base_field' => $field_names[$this->getOption('conditional_formatting_base_field_' . $i)]]);
        $conditional_formatting_operator[$i] = $operator_options[$this->getOption('conditional_formatting_operator_' . $i)];
        $conditional_formatting_compare_to[$i] = '"' . $this->getOption('conditional_formatting_compare_to_' . $i) . '"';
        $conditional_formatting_background_color[$i] = $this->getOption('conditional_formatting_background_color_' . $i) ? $this->t('then apply RGB code row background color: @rgb_code', ['@rgb_code' => $this->getOption('conditional_formatting_background_color_' . $i)]) : $this->t('No background color');

        $conditional_formatting_value[$i] = implode(' ', [
          $conditional_formatting_base_field[$i],
          $conditional_formatting_operator[$i],
          $conditional_formatting_compare_to[$i],
          $conditional_formatting_background_color[$i],
        ]);
      }
    }

    // Add category for Excel conditional formatting.
    $categories['excel_conditional_formatting'] = [
      'title' => $this->t('Excel conditional formatting'),
      'column' => 'second',
      'build' => [
        '#weight' => -1,
      ],
    ];

    // Add options for Excel conditional formatting.
    for ($i = 0; $i <= 4; $i++) {
      $options['excel_conditional_formatting_rules_' . $i] = [
        'category' => 'excel_conditional_formatting',
        'title' => $this->t('Rule'),
        'value' => $conditional_formatting_value[$i],
      ];
    }

    // Add filename to the summary if set.
    if ($this->getOption('filename')) {
      $options['path']['value'] .= $this->t(': (@filename)', ['@filename' => $this->getOption('filename')]);
    }

    // Display the selected format from the style plugin if available.
    $style_options = $this->getOption('style')['options'];
    if (!empty($style_options['formats'])) {
      $options['style']['value'] .= $this->t(': (@export_format)', ['@export_format' => reset($style_options['formats'])]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    switch ($form_state->get('section')) {
      case 'style':
        // Remove the 'serializer' and 'data_export'
        // (if available) options to avoid confusion.
        unset($form['style']['type']['#options']['serializer']);
        unset($form['style']['type']['#options']['data_export']);
        break;

      case 'path':
        $form['filename'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Filename'),
          '#default_value' => $this->getOption('filename'),
          '#description' => $this->t('The filename that will be suggested to the browser for downloading purposes. You may include replacement patterns from the list below.'),
        ];
        // Support tokens.
        $this->globalTokenForm($form, $form_state);
        break;

      case 'format_header':
        $form['#title'] .= $this->t('Format header');
        $form['header_bold'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Header Bold'),
          '#default_value' => $this->getOption('header_bold'),
          '#description' => $this->t('Do you want to make the header (first row) of the worksheet bold?'),
        ];
        $form['header_italic'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Header Italic'),
          '#default_value' => $this->getOption('header_italic'),
          '#description' => $this->t('Do you want to make the header (first row) of the worksheet italic?'),
        ];
        $form['header_background_color'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Header Background Color'),
          '#default_value' => $this->getOption('header_background_color'),
          '#description' => $this->t("Give the RGB code for the background color of the worksheet's header. Leave empty for default color."),
          '#size' => 6,
          '#maxlength' => 6,
        ];
        break;
    }

    $field_names = $this->view->getDisplay()->getFieldLabels();
    $form['#title'] .= $this->t('Conditional formatting rules');

    for ($i = 0; $i <= 4; $i++) {
      if ($form_state->get('section') === 'excel_conditional_formatting_rules_' . $i) {
        $form['conditional_formatting_base_field_' . $i] = [
          '#type' => 'select',
          '#options' => $field_names,
          '#empty_value' => 'Select a field',
          '#title' => $this->t('Field used to compare to text'),
          '#default_value' => $this->getOption('conditional_formatting_base_field_' . $i),
        ];
        $form['conditional_formatting_operator_' . $i] = [
          '#type' => 'select',
          '#options' => ['=', '<>'],
          '#empty_value' => 'Select an operator',
          '#title' => $this->t('Operator'),
          '#default_value' => $this->getOption('conditional_formatting_operator_' . $i),
        ];
        $form['conditional_formatting_compare_to_' . $i] = [
          '#type' => 'textfield',
          '#empty_value' => 'Select an operator',
          '#title' => $this->t('Text to compare to'),
          '#default_value' => $this->getOption('conditional_formatting_compare_to_' . $i),
        ];
        $form['conditional_formatting_background_color_' . $i] = [
          '#type' => 'textfield',
          '#title' => $this->t('Row Background Color'),
          '#default_value' => $this->getOption('conditional_formatting_background_color_' . $i),
          '#description' => $this->t("Give the RGB code for the background color of the row. Leave empty for default color."),
          '#size' => 6,
          '#maxlength' => 6,
        ];
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    // Validate Excel sheet header formatting functionality fields.
    if ($form_state->get('section') == 'format_header') {
      $errors = $this->validateRgbValue($form_state->getValue('header_background_color'));
      foreach ($errors as $error) {
        $form_state->setError($form['header_background_color'], $error);
      }
    }

    // Validate Excel conditional formatting functionality fields.
    for ($i = 0; $i <= 4; $i++) {
      if ($form_state->get('section') === 'excel_conditional_formatting_rules_' . $i) {
        // If one of conditional_formatting_base_field,
        // conditional_formatting_operator or conditional_formatting_compare_to
        // is set, all of them have to be set.
        $conditional_formatting_base_field_value[$i] = $form_state->getValue('conditional_formatting_base_field_' . $i);
        $conditional_formatting_operator_value[$i] = $form_state->getValue('conditional_formatting_operator_' . $i);
        $conditional_formatting_compare_to_value[$i] = $form_state->getValue('conditional_formatting_compare_to_' . $i);

        if ($conditional_formatting_base_field_value[$i] !== 'Select a field') {
          if ($conditional_formatting_operator_value[$i] === 'Select an operator' || $conditional_formatting_compare_to_value[$i] === '') {
            $form_state->setError($form['conditional_formatting_base_field'], $this->t('Either all or none of the following three inputs must be set: "Field used to compare to text", "Operator" and "Text to compare to".'));
          }
        }
        if ($conditional_formatting_operator_value[$i] !== 'Select an operator') {
          if ($conditional_formatting_base_field_value[$i] === 'Select a field' || $conditional_formatting_compare_to_value[$i] === '') {
            $form_state->setError($form['conditional_formatting_operator'], $this->t('Either all or none of the following three inputs must be set: "Field used to compare to text", "Operator" and "Text to compare to".'));
          }
        }
        if ($conditional_formatting_compare_to_value[$i] !== '') {
          if ($conditional_formatting_base_field_value[$i] === 'Select a field' || $conditional_formatting_operator_value[$i] === 'Select an operator') {
            $form_state->setError($form['conditional_formatting_compare_to'], $this->t('Either all or none of the following three inputs must be set: "Field used to compare to text", "Operator" and "Text to compare to".'));
          }
        }

        $form_state->setValue('conditional_formatting_background_color_' . $i, strtoupper($form_state->getValue('conditional_formatting_background_color_' . $i)));
        $errors = $this->validateRgbValue($form_state->getValue('conditional_formatting_background_color_' . $i));
        foreach ($errors as $error) {
          $form_state->setError($form['conditional_formatting_background_color_' . $i], $error);
        }
      }
    }

    // Uppercase the background color RGB values.
    $form_state->setValue('header_background_color', strtoupper($form_state->getValue('header_background_color')));
  }

  /**
   * Validates the header background color field of the display.
   *
   * @param string $rgb_value
   *   The rgb value to validate.
   *
   * @return array
   *   A list of error strings.
   */
  protected function validateRgbValue($rgb_value) {
    $errors = [];
    if ($rgb_value !== '' && !preg_match('/^([a-f0-9]{6})$/iD', strtolower($rgb_value))) {
      $errors[] = $this->t('Background color must be a 6-digit hexadecimal value.');
    }

    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);

    $section = $form_state->get('section');
    switch ($section) {
      case 'path':
        $this->setOption('filename', $form_state->getValue('filename'));
        break;

      case 'format_header':
        $this->setOption('header_bold', $form_state->getValue('header_bold'));
        $this->setOption('header_italic', $form_state->getValue('header_italic'));
        $this->setOption('header_background_color', $form_state->getValue('header_background_color'));
        break;
    }

    for ($i = 0; $i <= 4; $i++) {
      if ($section === 'excel_conditional_formatting_rules_' . $i) {
        $this->setOption('conditional_formatting_base_field_' . $i, $form_state->getValue('conditional_formatting_base_field_' . $i));
        $this->setOption('conditional_formatting_operator_' . $i, $form_state->getValue('conditional_formatting_operator_' . $i));
        $this->setOption('conditional_formatting_compare_to_' . $i, $form_state->getValue('conditional_formatting_compare_to_' . $i));
        $this->setOption('conditional_formatting_background_color_' . $i, $form_state->getValue('conditional_formatting_background_color_' . $i));
      }
    }
  }

}
