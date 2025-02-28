<?php

namespace Drupal\image_effects\Plugin\ImageEffect;

use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\image\ConfigurableImageEffectBase;

/**
 * Filter image using convolution.
 *
 * @ImageEffect(
 *   id = "image_effects_convolution",
 *   label = @Translation("Convolution"),
 *   description = @Translation("Filter image using convolution.")
 * )
 */
class ConvolutionImageEffect extends ConfigurableImageEffectBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'kernel' => [[0, 0, 0], [0, 0, 0], [0, 0, 0]],
      'divisor' => 0,
      'offset' => 0,
      'label' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return [
      '#theme' => 'image_effects_convolution_summary',
      '#data' => $this->configuration,
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['#attached']['library'][] = 'image_effects/image_effects.admin.convolution';

    $form['kernel'] = [
      '#type' => 'item',
      '#title' => $this->t('Kernel matrix'),
      '#description' => $this->t('Entries sum: <span class="kernel-matrix-sum">0</span>.'),
      '#required' => TRUE,
    ];
    $form['kernel']['entries'] = [];
    for ($i = 0; $i < 3; $i++) {
      $form['kernel']['entries'][$i] = [
        '#type' => 'container',
      ];
      for ($j = 0; $j < 3; $j++) {
        $form['kernel']['entries'][$i][$j] = [
          '#type' => 'number',
          '#title' => $this->t("Matrix entry (@i,@j)", ['@i' => $i, '@j' => $j]),
          '#title_display' => 'invisible',
          '#default_value' => $this->configuration['kernel'][$i][$j],
          '#required' => TRUE,
          '#wrapper_attributes' => ['class' => ['kernel-entry']],
        ];
      }
    }
    $form['divisor'] = [
      '#type' => 'number',
      '#title' => $this->t('Divisor'),
      '#description'  => $this->t('Typically the matrix entries sum (normalization).'),
      '#default_value' => $this->configuration['divisor'],
      '#required' => TRUE,
      '#size' => 3,
    ];
    $form['offset'] = [
      '#type' => 'number',
      '#title' => $this->t('Offset'),
      '#description'  => $this->t('This value is added to the division result.'),
      '#default_value' => $this->configuration['offset'],
      '#required' => TRUE,
      '#size' => 3,
    ];
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#description'  => $this->t('A label to identify this filter effect.'),
      '#default_value' => $this->configuration['label'],
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['kernel'] = $form_state->getValue('kernel')['entries'];
    $this->configuration['divisor'] = $form_state->getValue('divisor');
    $this->configuration['offset'] = $form_state->getValue('offset');
    $this->configuration['label'] = $form_state->getValue('label');
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    return $image->apply('convolution', [
      'kernel' => $this->configuration['kernel'],
      'divisor' => $this->configuration['divisor'],
      'offset' => $this->configuration['offset'],
    ]);
  }

}
