<?php

namespace Drupal\bootstrap_styles\Plugin\BootstrapStyles\StylesGroup;

use Drupal\bootstrap_styles\StylesGroup\StylesGroupPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class Background.
 *
 * @package Drupal\bootstrap_styles\Plugin\StylesGroup
 *
 * @StylesGroup(
 *   id = "background",
 *   title = @Translation("Background"),
 *   weight = 1,
 *   icon = "bootstrap_styles/images/plugins/background-icon.svg"
 * )
 */
class Background extends StylesGroupPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['background'] = [
      '#type' => 'details',
      '#title' => $this->t('Background'),
      '#open' => FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildStyleFormElements(array &$form, FormStateInterface $form_state, $storage) {
    $form['background_type'] = [
      '#type' => 'radios',
      '#options' => [],
      '#title' => $this->t('Background type'),
      '#title_display' => 'invisible',
      '#default_value' => NULL,
      '#validated' => TRUE,
      '#attributes' => [
        'class' => ['bs_col--full', 'bs_background--type'],
      ],
      '#disable_live_preview' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitStyleFormElements(array $group_elements) {

    // When the Background Image config field has a selected media target entity image id.
    if (isset($group_elements['background_image']) && $group_elements['background_image'] !== '') {
      $group_elements['background_type'] = 'image';
    }

    // When the Background Video config field has a selected media target entity video id.
    if (isset($group_elements['background_video']) && $group_elements['background_video'] !== '') {
      $group_elements['background_type'] = 'video';
    }

    return [
      'background' => [
        'background_type' => $group_elements['background_type'],
      ],
    ];
  }

}
