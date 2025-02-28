<?php

namespace Drupal\bootstrap_styles\Plugin\BootstrapStyles\StylesGroup;

use Drupal\bootstrap_styles\StylesGroup\StylesGroupPluginBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class Typography.
 *
 * @package Drupal\bootstrap_styles\Plugin\StylesGroup
 *
 * @StylesGroup(
 *   id = "typography",
 *   title = @Translation("Typography"),
 *   weight = 2,
 *   icon = "bootstrap_styles/images/plugins/typography-icon.svg"
 * )
 */
class Typography extends StylesGroupPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['typography'] = [
      '#type' => 'details',
      '#title' => $this->t('Typography'),
      '#open' => FALSE,
    ];

    return $form;
  }

}
