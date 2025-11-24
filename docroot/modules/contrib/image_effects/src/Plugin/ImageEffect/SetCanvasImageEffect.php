<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Attribute\ImageEffect;
use Drupal\image\ConfigurableImageEffectBase;
use Drupal\image_effects\Component\ImageUtility;

/**
 * Defines the size of the working canvas and background color.
 */
#[ImageEffect(
  id: 'image_effects_set_canvas',
  label: new TranslatableMarkup('Set canvas'),
  description: new TranslatableMarkup('Define the size of the working canvas and background color, this controls the dimensions of the output image.'),
)]
class SetCanvasImageEffect extends ConfigurableImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return NestedArray::mergeDeep([
      'canvas_size' => 'exact',
      'canvas_color' => NULL,
      'exact' => [
        'width' => '',
        'height' => '',
        'placement' => 'center-center',
        'x_offset' => 0,
        'y_offset' => 0,
      ],
      'relative' => [
        'left' => 0,
        'right' => 0,
        'top' => 0,
        'bottom' => 0,
      ],
    ], parent::defaultConfiguration());
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $data = $this->configuration;

    $data['color_info'] = [
      '#theme' => 'image_effects_color_detail',
      '#color' => $this->configuration['canvas_color'],
      '#border' => TRUE,
      '#border_color' => 'matchLuma',
    ];

    return [
      '#theme' => 'image_effects_set_canvas_summary',
      '#data' => $data,
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['canvas_size'] = [
      '#type' => 'radios',
      '#title' => $this->t('Canvas size'),
      '#default_value' => $this->configuration['canvas_size'],
      '#options' => [
        'exact' => $this->t('Exact size'),
        'relative' => $this->t('Relative size'),
      ],
      '#required' => TRUE,
    ];

    // Exact size canvas.
    $form['exact'] = [
      '#type' => 'details',
      '#title' => $this->t('Exact size'),
      '#description'  => $this->t('Set the canvas to a precise size, possibly cropping the image. Use to start with a known size.'),
      '#open' => TRUE,
      '#collapsible' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="data[canvas_size]"]' => ['value' => 'exact'],
        ],
      ],
    ];
    $form['exact']['width'] = [
      '#type' => 'image_effects_px_perc',
      '#title' => $this->t('Width'),
      '#default_value' => $this->configuration['exact']['width'],
      '#description' => $this->t('Enter a value, and specify if pixels or percent. Leave blank to keep source image width.'),
      '#size' => 6,
      '#maxlength' => 6,
    ];
    $form['exact']['height'] = [
      '#type' => 'image_effects_px_perc',
      '#title' => $this->t('Height'),
      '#default_value' => $this->configuration['exact']['height'],
      '#description' => $this->t('Enter a value, and specify if pixels or percent. Leave blank to keep source image height.'),
      '#size' => 6,
      '#maxlength' => 6,
    ];
    $form['exact']['placement'] = [
      '#type' => 'radios',
      '#title' => $this->t('Placement'),
      '#options' => [
        'left-top' => $this->t('Top left'),
        'center-top' => $this->t('Top center'),
        'right-top' => $this->t('Top right'),
        'left-center' => $this->t('Center left'),
        'center-center' => $this->t('Center'),
        'right-center' => $this->t('Center right'),
        'left-bottom' => $this->t('Bottom left'),
        'center-bottom' => $this->t('Bottom center'),
        'right-bottom' => $this->t('Bottom right'),
      ],
      '#theme' => 'image_anchor',
      '#default_value' => $this->configuration['exact']['placement'],
      '#description' => $this->t('Position of the image on the canvas.'),
    ];
    $form['exact']['x_offset'] = [
      '#type'  => 'number',
      '#title' => $this->t('Horizontal offset'),
      '#field_suffix'  => 'px',
      '#description'   => $this->t('Additional horizontal offset from placement.'),
      '#default_value' => $this->configuration['exact']['x_offset'],
      '#maxlength' => 4,
      '#size' => 4,
    ];
    $form['exact']['y_offset'] = [
      '#type'  => 'number',
      '#title' => $this->t('Vertical offset'),
      '#field_suffix'  => 'px',
      '#description'   => $this->t('Additional vertical offset from placement.'),
      '#default_value' => $this->configuration['exact']['y_offset'],
      '#maxlength' => 4,
      '#size' => 4,
    ];

    // Relative size canvas.
    $form['relative'] = [
      '#type' => 'details',
      '#title' => $this->t('Relative size'),
      '#description'  => $this->t('Set the canvas to a relative size, based on the current image dimensions. Use to add simple borders or expand by a fixed amount. Negative values may crop the image.'),
      '#open' => TRUE,
      '#collapsible' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="data[canvas_size]"]' => ['value' => 'relative'],
        ],
      ],
    ];
    $form['relative']['left'] = [
      '#type'  => 'number',
      '#title' => $this->t('Left margin'),
      '#default_value' => $this->configuration['relative']['left'],
      '#maxlength' => 4,
      '#size' => 4,
      '#description' => $this->t('Enter an offset in pixels.'),
    ];
    $form['relative']['right'] = [
      '#type'  => 'number',
      '#title' => $this->t('Right margin'),
      '#default_value' => $this->configuration['relative']['right'],
      '#maxlength' => 4,
      '#size' => 4,
      '#description' => $this->t('Enter an offset in pixels.'),
    ];
    $form['relative']['top'] = [
      '#type'  => 'number',
      '#title' => $this->t('Top margin'),
      '#default_value' => $this->configuration['relative']['top'],
      '#maxlength' => 4,
      '#size' => 4,
      '#description' => $this->t('Enter an offset in pixels.'),
    ];
    $form['relative']['bottom'] = [
      '#type'  => 'number',
      '#title' => $this->t('Bottom margin'),
      '#default_value' => $this->configuration['relative']['bottom'],
      '#maxlength' => 4,
      '#size' => 4,
      '#description' => $this->t('Enter an offset in pixels.'),
    ];

    // Canvas color.
    $form['canvas_color'] = [
      '#type' => 'image_effects_color',
      '#title' => $this->t('Canvas color'),
      '#allow_null' => TRUE,
      '#allow_opacity' => TRUE,
      '#description'  => $this->t("This will have the effect of adding colored (or transparent) margins around the image."),
      '#default_value' => $this->configuration['canvas_color'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $this->configuration = $form_state->getValues();
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    $data = [];
    $data['canvas_color'] = $this->configuration['canvas_color'];

    // Get resulting dimensions.
    $dimensions = $this->getDimensions($image->getWidth(), $image->getHeight());
    $data['width'] = $dimensions['width'];
    $data['height'] = $dimensions['height'];

    // Get offset of original image.
    if ($this->configuration['canvas_size'] === 'exact') {
      [$x_pos, $y_pos] = explode('-', $this->configuration['exact']['placement']);
      $data['x_pos'] = ImageUtility::getKeywordOffset($x_pos, $data['width'], $image->getWidth()) + $this->configuration['exact']['x_offset'];
      $data['y_pos'] = ImageUtility::getKeywordOffset($y_pos, $data['height'], $image->getHeight()) + $this->configuration['exact']['y_offset'];
    }
    else {
      $data['x_pos'] = $this->configuration['relative']['left'];
      $data['y_pos'] = $this->configuration['relative']['top'];
    }

    // All the math is done, now defer to the toolkit in use.
    return $image->apply('set_canvas', $data);
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions, $uri) {
    $dimensions['width'] = $dimensions['width'] ? (int) $dimensions['width'] : NULL;
    $dimensions['height'] = $dimensions['height'] ? (int) $dimensions['height'] : NULL;

    if ($dimensions['width'] === NULL || $dimensions['height'] === NULL) {
      $dimensions['width'] = $dimensions['height'] = NULL;
      return;
    }

    ['width' => $dimensions['width'], 'height' => $dimensions['height']] = $this->getDimensions($dimensions['width'], $dimensions['height']);
  }

  /**
   * Calculate resulting image dimensions.
   *
   * @param int $source_width
   *   Source image width.
   * @param int $source_height
   *   Source image height.
   *
   * @return array
   *   Associative array.
   *   - width: Integer with the derivative image width.
   *   - height: Integer with the derivative image height.
   */
  protected function getDimensions(int $source_width, int $source_height): array {
    $dimensions = [];
    if ($this->configuration['canvas_size'] === 'exact') {
      // Exact size.
      $tmp_width = $this->configuration['exact']['width'] ?: $source_width;
      $tmp_height = $this->configuration['exact']['height'] ?: $source_height;
      $dimensions['width'] = ImageUtility::percentFilter($tmp_width, $source_width);
      $dimensions['height'] = ImageUtility::percentFilter($tmp_height, $source_height);
    }
    else {
      // Relative size.
      $dimensions['width'] = $source_width + $this->configuration['relative']['left'] + $this->configuration['relative']['right'];
      $dimensions['height'] = $source_height + $this->configuration['relative']['top'] + $this->configuration['relative']['bottom'];
    }
    return $dimensions;
  }

}
