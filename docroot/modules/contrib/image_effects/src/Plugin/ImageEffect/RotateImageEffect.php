<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Attribute\ImageEffect;
use Drupal\image\ConfigurableImageEffectBase;
use Drupal\image_effects\Component\Rectangle;

/**
 * Rotates an image.
 */
#[ImageEffect(
  id: 'image_effects_rotate',
  label: new TranslatableMarkup('Rotate [by Image Effects]'),
  description: new TranslatableMarkup('Rotate the image by a specified angle, optionally setting the background color.'),
)]
class RotateImageEffect extends ConfigurableImageEffectBase {

  /**
   * Angle determination method constants.
   */
  protected const EXACT = 'exact';
  protected const PSEUDO_RANDOM = 'pseudorandom';
  protected const RANDOM = 'random';

  /**
   * Max positive signed 32-bit integer.
   */
  protected const MAX_INT_32 = 2147483647;

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    switch ($this->configuration['method']) {
      case static::PSEUDO_RANDOM:
        $degrees = $this->getRotationFromUri($image->getSource(), $this->configuration['degrees']);
        break;

      case static::RANDOM:
        $max = (int) abs((float) $this->configuration['degrees']);
        $degrees = rand(-$max, $max);
        break;

      case static::EXACT:
      default:
        $degrees = $this->configuration['degrees'];
        break;

    }

    if (!$image->apply('rotate_ie', [
      'degrees' => $degrees,
      'background' => $this->configuration['background_color'],
      'fallback_transparency_color' => $this->configuration['fallback_transparency_color'],
    ])) {
      $this->logger->error('Image rotate failed using the %toolkit toolkit on %path (%mimetype, %dimensions)', [
        '%toolkit' => $image->getToolkitId(),
        '%path' => $image->getSource(),
        '%mimetype' => $image->getMimeType(),
        '%dimensions' => $image->getWidth() . 'x' . $image->getHeight(),
      ]);
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function transformDimensions(array &$dimensions, $uri) {
    $dimensions['width'] = $dimensions['width'] ? (int) $dimensions['width'] : NULL;
    $dimensions['height'] = $dimensions['height'] ? (int) $dimensions['height'] : NULL;

    // If the current dimensions are set, then the new dimensions can be
    // determined.
    if ($dimensions['width'] && $dimensions['height']) {
      switch ($this->configuration['method']) {
        case static::PSEUDO_RANDOM:
          $degrees = $this->getRotationFromUri($uri, $this->configuration['degrees']);
          break;

        case static::RANDOM:
          // For a random rotate, the new dimensions can not be determined.
          $dimensions['width'] = $dimensions['height'] = NULL;
          return;

        case static::EXACT:
        default:
          $degrees = $this->configuration['degrees'];
          break;

      }

      $rect = new Rectangle($dimensions['width'], $dimensions['height']);
      $rect = $rect->rotate($degrees);
      $dimensions['width'] = $rect->getBoundingWidth();
      $dimensions['height'] = $rect->getBoundingHeight();
    }
    else {
      $dimensions['width'] = $dimensions['height'] = NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $data = $this->configuration;

    if ($data['background_color']) {
      $data['background_color_detail'] = [
        '#theme' => 'image_effects_color_detail',
        '#color' => $data['background_color'],
        '#border' => TRUE,
        '#border_color' => 'matchLuma',
      ];
    }

    $summary = [
      '#theme' => 'image_effects_rotate_summary',
      '#data' => $data,
    ];
    $summary += parent::getSummary();

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'degrees' => 0,
      'background_color' => NULL,
      'fallback_transparency_color' => '#FFFFFF',
      'method' => static::EXACT,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['degrees'] = [
      '#type' => 'number',
      '#default_value' => $this->configuration['degrees'],
      '#title' => $this->t('Rotation angle'),
      '#description' => $this->t('The number of degrees the image should be rotated. Positive numbers are clockwise, negative are counter-clockwise.'),
      '#field_suffix' => '°',
      '#required' => TRUE,
    ];
    $form['background_color'] = [
      '#type' => 'image_effects_color',
      '#title' => $this->t('Background color'),
      '#allow_null' => TRUE,
      '#allow_opacity' => TRUE,
      '#description'  => $this->t("The background color to use for the areas of the image that remain exposed by the rotation."),
      '#default_value' => $this->configuration['background_color'],
    ];
    $form['transparency_fallback'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Fallback for transparent background'),
      '#states' => [
        'visible' => [
          ':input[name="data[background_color][container][transparent]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['transparency_fallback']['fallback_transparency_color'] = [
      '#description'  => $this->t('Some image formats do not support transparency. This color will be used in place of transparent in such cases.'),
      '#type' => 'image_effects_color',
      '#default_value' => $this->configuration['fallback_transparency_color'],
    ];
    $form['method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Angle determination'),
      '#default_value' => $this->configuration['method'],
      '#options' => [
        static::EXACT => $this->t('<b>Exact.</b> The image will be rotated exactly by the degrees indicated.'),
        static::PSEUDO_RANDOM => $this->t('<b>Pseudo-random.</b> The image will be rotated within a range +/- the degrees indicated, based on a pseudo-random algorithm that uses the image source URI for input.'),
        static::RANDOM => $this->t('<b>Random.</b> The image will be rotated randomly within a range +/- the degrees indicated.'),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['degrees'] = $form_state->getValue('degrees');
    $this->configuration['background_color'] = $form_state->getValue('background_color');
    $this->configuration['fallback_transparency_color'] = $form_state->getValue([
      'transparency_fallback',
      'fallback_transparency_color',
    ]);
    $this->configuration['method'] = $form_state->getValue('method');
  }

  /**
   * Returns a pseudo-random angle for rotation.
   *
   * Uses a CRC32B hash of the URI, concatenated with the configured degrees and
   * background color to calculate the pseudo-random angle.
   *
   * @param string $uri
   *   The URI of the original file.
   * @param int $max_degrees
   *   The maximum rotation angle.
   *
   * @return float
   *   An angle in the range from -$max_degrees to +$max_degrees.
   */
  protected function getRotationFromUri(string $uri, int $max_degrees): float {
    if ($max_degrees === 0) {
      return 0;
    }
    $max_abs_degrees = abs($max_degrees);
    $seed = (hexdec(hash('crc32b', $uri . $this->configuration['degrees'] . $this->configuration['background_color'])) / static::MAX_INT_32) - 1;
    return $max_abs_degrees * $seed;
  }

}
