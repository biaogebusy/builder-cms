<?php

namespace Drupal\bootstrap_styles\Plugin\BootstrapStyles\StylesGroup;

use Drupal\bootstrap_styles\StylesGroup\StylesGroupPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class Border.
 *
 * @package Drupal\bootstrap_styles\Plugin\StylesGroup
 *
 * @StylesGroup(
 *   id = "border",
 *   title = @Translation("Border"),
 *   weight = 4,
 *   icon = "bootstrap_styles/images/plugins/border-icon.svg"
 * )
 */
class Border extends StylesGroupPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['border'] = [
      '#type' => 'details',
      '#title' => $this->t('Border'),
      '#open' => FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildStyleFormElements(array &$form, FormStateInterface $form_state, $storage) {
    $form['border_preview'] = [
      '#theme' => 'border_preview',
    ];

    return $form;
  }

}
