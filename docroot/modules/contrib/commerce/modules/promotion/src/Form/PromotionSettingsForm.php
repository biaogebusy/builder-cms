<?php

namespace Drupal\commerce_promotion\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class PromotionSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['commerce_promotion.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'commerce_promotion_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $form['enforce_weight_ordering'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Apply all promotions by sort order to enforce compatibility, including those with coupons'),
      '#description' => $this->t('If disabled, coupon based promotions will apply first regardless of compatibility rules.'),
      '#config_target' => 'commerce_promotion.settings:enforce_weight_ordering',
    ];

    return $form;
  }

}
