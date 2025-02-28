<?php

namespace Drupal\bootstrap_styles\Plugin\BootstrapStyles\StylesGroup;

use Drupal\bootstrap_styles\StylesGroup\StylesGroupPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class Shadow.
 *
 * @package Drupal\bootstrap_styles\Plugin\StylesGroup
 *
 * @StylesGroup(
 *   id = "shadow",
 *   title = @Translation("Shadow"),
 *   weight = 5,
 *   icon = "bootstrap_styles/images/plugins/shadow-icon.svg"
 * )
 */
class Shadow extends StylesGroupPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['shadow'] = [
      '#type' => 'details',
      '#title' => $this->t('Shadow'),
      '#open' => FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildStyleFormElements(array &$form, FormStateInterface $form_state, $storage) {
    $form['shadow_preview'] = [
      '#theme' => 'shadow_preview',
    ];

    return $form;
  }

}
