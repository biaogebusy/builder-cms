<?php

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\image\ConfigurableImageEffectBase;
use Drupal\image_effects\Component\ImageUtility;

/**
 * Directly enter ImageMagick/GraphicsMagick command line arguments.
 *
 * @ImageEffect(
 *   id = "image_effects_imagemagick_arguments",
 *   label = @Translation("ImageMagick arguments"),
 *   description = @Translation("Directly enter ImageMagick/GraphicsMagick command line arguments.")
 * )
 */
class ImagemagickArgumentsImageEffect extends ConfigurableImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'command_line' => '',
      'dimensions_method' => 'keep',
      'width' => '',
      'height' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return [
      '#theme' => 'image_effects_imagemagick_arguments_summary',
      '#data' => $this->configuration,
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['command_line'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Command line arguments'),
      '#default_value' => $this->configuration['command_line'],
      '#description' => $this->t('<strong>Enter the command line <em>arguments</em> only.</strong><br/>Remember to escape parenthesis (and remember escaping is platform specific, use \ on *nix and ^ on Windows).<br />Do not add an input or output file. Drupal ImageAPI will add "convert" before and a "-quality" option after based on the toolkit configuration settings.'),
      '#rows' => '5',
      '#resizable' => 'TRUE',
      '#required' => 'TRUE',
    ];
    $form['dimensions_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Dimensions'),
      '#description' => $this->t("Dimensions are stored in the Drupal image object and used for output HTML image (img) element <em>width</em> and <em>height</em> attributes. <em>They have no effect on the real size of the image, but may affect the display.</em><br />It's not possible to detect the dimensions of the Imagemagick command's result image in the scope of this effect, so you have to decide what dimensions to pass to ImageAPI here."),
      '#default_value' => $this->configuration['dimensions_method'],
      '#options' => [
        'keep' => $this->t("<strong>Keep dimensions.</strong> Pass through the current image dimensions. Use if the arguments specified above do not change image size."),
        'value' => $this->t('<strong>Manual input.</strong> If you know the size of the image that the arguments specified above will produce, specify them below, either in pixels or as % of the current image dimensions.'),
        'strip' => $this->t("<strong>Strip dimensions.</strong> This won't pass on any image dimensions. Images will have no HTML <em>width</em> and <em>height</em> attributes if this effect is the last in the image style. Use this if you have another dimension altering effect after this."),
      ],
      '#required' => TRUE,
    ];
    $form['dimensions'] = [
      '#type' => 'details',
      '#title' => $this->t('Width and Height'),
      '#description'  => $this->t('Indicate width and height.'),
      '#open' => TRUE,
      '#collapsible' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="data[dimensions_method]"]' => ['value' => 'value'],
        ],
      ],
    ];
    $form['dimensions']['width'] = [
      '#type' => 'image_effects_px_perc',
      '#title' => $this->t('Width'),
      '#default_value' => $this->configuration['width'],
      '#required' => FALSE,
      '#size' => 5,
    ];
    $form['dimensions']['height'] = [
      '#type' => 'image_effects_px_perc',
      '#title' => $this->t('Height'),
      '#default_value' => $this->configuration['height'],
      '#required' => FALSE,
      '#size' => 5,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['command_line'] = $form_state->getValue('command_line');
    $this->configuration['dimensions_method'] = $form_state->getValue('dimensions_method');
    $this->configuration['width'] = $form_state->getValue(['dimensions', 'width']);
    $this->configuration['height'] = $form_state->getValue(['dimensions', 'height']);
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    // Get resulting dimensions.
    $d = $this->getDimensions($image->getWidth(), $image->getHeight());

    return $image->apply('imagemagick_arguments', [
      'command_line' => str_replace(["\r", "\n"], ' ', $this->configuration['command_line']),
      'width' => $d['width'],
      'height' => $d['height'],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions, $uri) {
    $d = $this->getDimensions($dimensions['width'], $dimensions['height']);
    $dimensions['width'] = $d['width'];
    $dimensions['height'] = $d['height'];
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
  protected function getDimensions($source_width, $source_height) {
    $dimensions = [];
    switch ($this->configuration['dimensions_method']) {
      case 'strip':
        // Strip dimensions of the result.
        $dimensions['width'] = $dimensions['height'] = NULL;
        break;

      case 'keep':
        // Keep original image dimensions.
        $dimensions['width'] = $source_width;
        $dimensions['height'] = $source_height;
        break;

      case 'value':
        // Manually specified dimensions.
        $dimensions['width'] = ImageUtility::percentFilter($this->configuration['width'], $source_width);
        $dimensions['height'] = ImageUtility::percentFilter($this->configuration['height'], $source_height);
        break;

    }
    return $dimensions;
  }

}
