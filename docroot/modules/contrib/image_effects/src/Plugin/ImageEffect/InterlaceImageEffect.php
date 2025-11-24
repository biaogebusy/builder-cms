<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Attribute\ImageEffect;
use Drupal\image\ConfigurableImageEffectBase;

/**
 * Specify the type of interlacing scheme.
 */
#[ImageEffect(
  id: 'image_effects_interlace',
  label: new TranslatableMarkup('Interlace'),
  description: new TranslatableMarkup('Specify the type of interlacing scheme.'),
)]
class InterlaceImageEffect extends ConfigurableImageEffectBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'type' => 'Plane',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return [
      '#theme' => 'image_effects_interlace_summary',
      '#data' => $this->configuration,
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Interlace type'),
      '#description'  => $this->t('Use <strong>Line</strong> or <strong>Plane</strong> to create an interlaced PNG or GIF or progressive JPEG image. <strong>This setting is not relevant for the GD image toolkit.</strong>'),
      '#default_value' => $this->configuration['type'],
      '#required' => TRUE,
      '#options' => [
        'Line' => $this->t('Line'),
        'Plane' => $this->t('Plane'),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['type'] = $form_state->getValue('type');
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    return $image->apply('interlace', ['type' => $this->configuration['type']]);
  }

}
