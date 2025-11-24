<?php

declare(strict_types=1);

namespace Drupal\ckeditor_font\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\editor\EditorInterface;

/**
 * CKEditor 5 Font (Family & Size) plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class Font extends CKEditor5PluginDefault implements CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
 * The default configuration for this plugin.
 *
 * @var string[][]
 */
  const DEFAULT_SIZES = [
    'tiny',
    'small',
    'default',
    'big',
    'huge',
  ];

  const DEFAULT_FONTS = [
    'default',
    'Arial, Helvetica, sans-serif',
    'Courier New, Courier, monospace',
    'Georgia, serif',
    'Lucida Sans Unicode, Lucida Grande, sans-serif',
    'Tahoma, Geneva, sans-serif',
    'Times New Roman, Times, serif',
    'Trebuchet MS, Helvetica, sans-serif',
    'Verdana, Geneva, sans-serif',
  ];

  const DEFAULT_CONFIGURATION = [
    'font_sizes' => [],
    'font_names' => [],
    'supportAllFamilyValues' => FALSE,
    'supportAllSizeValues' => FALSE,
  ];

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return static::DEFAULT_CONFIGURATION;
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    // Get static config.
    $font_all_options['fontSize']['options'] = (empty($this->getConfiguration()['font_sizes'])) ? static::DEFAULT_SIZES : $this->getConfiguration()['font_sizes'];
    $font_all_options['fontSize']['supportAllValues'] = $this->getConfiguration()['supportAllSizeValues'];
    $font_all_options['fontFamily']['options'] = (empty($this->getConfiguration()['font_names'])) ? static::DEFAULT_FONTS : $this->getConfiguration()['font_names'];
    $font_all_options['fontFamily']['supportAllValues'] = $this->getConfiguration()['supportAllFamilyValues'];

    // Convert config to ['title']['model'] sub-array.
    return $font_all_options;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\editor\Form\EditorImageDialog
   * @see editor_image_upload_settings_form()
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['font_sizes'] = [
      '#title' => $this->t('Font sizes'),
      '#type' => 'textarea',
      '#description' => $this->t('Enter font sizes on new lines. Sizes must be added with the following syntax:<br><code>123px|Size label</code><br><code>123em|Size label</code><br><code>123%|Size label</code>'),
      '#element_validate' => [
        [$this, 'validateFontSizeValue'],
      ],
    ];

    $font_size_selectors = '';
    if (!empty($this->configuration['font_sizes'])) {
      foreach ($this->configuration['font_sizes'] as $font_size) {
        // If using the awkward supportAllSizeValues, show just the
        // value without the schema.
        if ($this->configuration['supportAllSizeValues']) {
          $font_size_selectors .= sprintf("%s\n", $font_size);
        }
        else {
          $font_size_selectors .= sprintf("%s|%s\n", $font_size['model'], $font_size['title']);
        }
      }
    }
    $form['font_sizes']['#default_value'] = $font_size_selectors;

    $form['supportAllSizeValues'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Support all Font Size values'),
      '#description' => $this->t("By default the plugin removes any font-size value that does not match the plugin's configuration. It means that if you paste content with font sizes that the editor does not understand, the font-size attribute will be removed and the content will be displayed with the default size."),
      '#default_value' => $this->configuration['supportAllSizeValues'],
    ];

    $form['font_names'] = [
      '#title' => $this->t('Font families'),
      '#type' => 'textarea',
      '#description' => $this->t('Enter fonts on new lines. Fonts must be added with the following syntax:<br><code>Primary font, fallback1, fallback2</code>. Font label is automatically derived from the first font family.'),
      '#element_validate' => [
        [$this, 'validateFontValue'],
      ],
    ];

    if (!empty($this->configuration['font_names'])) {
      $font_name_selectors = '';
      foreach ($this->configuration['font_names'] as $font_name) {
        $font_name_selectors .= sprintf("%s\n", $font_name);
      }
      $form['font_names']['#default_value'] = $font_name_selectors;
    }
    else {
      $form['font_names']['#default_value'] = [];
    }

    $form['supportAllFamilyValues'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Support all Font Family values'),
      '#description' => $this->t("By default the plugin removes any font-family value that does not match the plugin's configuration. It means that if you paste content with font families that the editor does not understand, the font-family attribute will be removed and the content will be displayed with the default font."),
      '#default_value' => $this->configuration['supportAllFamilyValues'],
    ];

    return $form;
  }

  /**
   * The #element_validate handler for the "supportAllSizeValues" element.
   *
   * @param array $form
   *   The array representation of the Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateSupportAllSizeValues(array $form, FormStateInterface $form_state) {
    // Run a separate regex for font_sizes
    // Empty will fail because the CKE5 default contains non-numerical values
    // Check for font values that are non-numerical, throw error.
    // See https://ckeditor.com/docs/ckeditor5/latest/api/module_font_fontsize-FontSizeConfig.html#member-supportAllValues
    $fontSizes = $form_state->getValue('font_sizes');
    if (empty($fontSizes)) {
      $form_state->setError($form['supportAllSizeValues'], t("\'Support all Font Size values\' setting cannot be used with an empty Font Sizes configuration."));
    }
    elseif ($this->generateFontSetting($fontSizes, 'supportAllSizes') === FALSE) {
      $form_state->setError($form['supportAllSizeValues'], t("\'Support all Font Size values\' setting cannot be used with non-numeric Font Sizes configuration."));
    }
  }

  /**
   * The handler for the "font_names" element in settingsForm().
   *
   * @param array $element
   *   The CKEditor Element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateFontValue(array $element, FormStateInterface $form_state) {
    if ($this->generateFontSetting($element['#value'], 'family') === FALSE) {
      $form_state->setError($element, t('The provided list of fonts is syntactically incorrect.'));
    }
  }

  /**
   * The handler for the "font_sizes" element in settingsForm().
   *
   * @param array $element
   *   The CKEditor Element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateFontSizeValue(array $element, FormStateInterface $form_state) {
    if ($this->generateFontSetting($element['#value'], 'size') === FALSE) {
      $form_state->setError($element, t('The provided list of font sizes is syntactically incorrect.'));
    }
  }

  /**
   * Checks if the font size declaration follows acceptable values.
   *
   * @param mixed $font
   *   The font size declaration.
   *
   * @return mixed
   *   Returns FALSE if the format is wrong, otherwise
   *   it returns the $font value.
   */
  public static function validateSupportAllFontSize(string $font) {
    // Checks for if fontsize value is numerical OR numerical|label value.
    // This is an aggressive implementation, as generateFontValue
    // should split label from value, but good to have.
    $pattern = '@(^[0-9]*)+(\|)+(\S*)|(^[0-9]*$)@';
    if (!preg_match($pattern, $font)) {
      return FALSE;
    }

    return $font;
  }

  /**
   * Determine whether the font size declaration follows acceptable values.
   *
   * @param mixed $font
   *   The font size declaration.
   *
   * @return mixed
   *   Returns FALSE if the format is wrong, otherwise
   *   it returns the $font value.
   */
  public static function validateFontSize(string $font) {
    // Match for patterns:
    // 123px/pt/em/rem/%
    // see: d.o/node/3312951.
    $fontSizeKeywords = ['xx-small', 'x-small', 'small', 'medium', 'large',
      'x-large', 'xx-large', 'xxx-large', 'larger', 'smaller',
      'inherit', 'initial', 'revert', 'revert-layer', 'unset',
    ];
    // //(^((\s*((small|medium|large)+)|\d+(\.?\d+)?(px|em|%|pt|rem))))\|.*|$
    $pattern = '@(^((\s*((' . implode('|', $fontSizeKeywords) . ')+)|\d+(\.?\d+)?(px|em|%|pt|rem)?)))@';

    if (!preg_match($pattern, $font)) {
      return FALSE;
    }

    return $font;
  }

  /**
   * Migrated font families may have a pipe and label, remove them.
   *
   * @param mixed $font
   *   The font size declaration.
   *
   * @return string
   *   Returns $font after some processing
   */
  public static function validateFontFamily(string $font) {
    $font = preg_replace('@\|.*$@', "", $font);

    return $font;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // First, validate the supportAllSizeValues, as we need the values
    // of font_sizes and supportAllSizeValues to properly confirm the value.
    if ($form_state->getValue('supportAllSizeValues')) {
      $this->validateSupportAllSizeValues($form, $form_state);
    }

    // Convert font_sizes string into configuration value.
    $font_sizes = $this->generateFontSetting($form_state->getValue('font_sizes'), 'size', $form_state->getValue('supportAllSizeValues'));
    if (!empty($font_sizes)) {
      $form_state->setValue('font_sizes', $font_sizes);
    }
    else {
      $form_state->setValue('font_sizes', []);
    }

    // Convert font_names string into configuration value.
    $font_names = $this->generateFontSetting($form_state->getValue('font_names'), 'family');
    if (!empty($font_names)) {
      $form_state->setValue('font_names', $font_names);
    }
    else {
      $form_state->setValue('font_names', []);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['supportAllSizeValues'] = $form_state->getValue('supportAllSizeValues') ? TRUE : FALSE;
    $this->configuration['supportAllFamilyValues'] = $form_state->getValue('supportAllFamilyValues') ? TRUE : FALSE;
    $this->configuration['font_names'] = $form_state->getValue('font_names');
    $this->configuration['font_sizes'] = $form_state->getValue('font_sizes');
  }

  /**
   * Builds and validates each option into a CKE5 array.
   *
   * @param string $fonts
   *   A newline delimited string of fonts.
   *   Syntax differs for each, but standardizes to be:
   *   Font Configuration|Font Label.
   * @param string $type
   *   A selector for which configuration to validate.
   *   Acceptable values: size, family, supportAllSizes.
   * @param int $supportAllValues
   *   Handle the supportAllSizeValues configuration,
   *   which demands an int for each value.
   *
   * @return mixed
   *   Returns a font setting.
   */
  public static function generateFontSetting(string $fonts, string $type, int $supportAllValues = 0) {
    $fonts = trim($fonts);
    // Prepare return value and init values.
    $font_values = [];

    // Early-return when empty.
    if (empty($fonts)) {
      return $font_values;
    }

    // Standardize newline.
    $fonts = str_replace(["\r\n", "\r"], "\n", $fonts);

    // Loop through each value.
    foreach (explode("\n", $fonts) as $font) {
      $font = trim($font);

      // Ignore empty lines.
      if (empty($font)) {
        continue;
      }

      $font_value = [];
      switch ($type) {
        case 'family':
          $font_value = self::validateFontFamily($font);
          break;

        case 'size':
          [$size, $label] = explode('|', $font);

          if (empty(self::validateFontSize($size))) {
            return FALSE;
          }
          if ($supportAllValues) {
            // If supportAllValues exists, then caste to int.
            $size = intval($size);
            $font_value = $size;
          }
          else {
            // Build subarray for CKEditor5 consumption.
            $font_value['title'] = $label ? $label : $size;
            $font_value['model'] = $size;
          }
          break;

        case 'supportAllSizes':
          // Break out label from size.
          // Replace with str_contains in PHP8.
          [$size, $label] = explode('|', $font);

          if (empty(self::validateSupportAllFontSize($size))) {
            return FALSE;
          }
          break;
      }
      $font_values[] = $font_value;
    }

    // Special casing for supportAllSizes.
    if ($type == 'supportAllSizes') {
      return TRUE;
    }

    // Otherwise, return generated array.
    return $font_values;
  }

}
