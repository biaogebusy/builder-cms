<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConstraintFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * Provides funcationality for IpConstraintFieldValidationRule.
 *
 * @FieldValidationRule(
 *   id = "ip_constraint_rule",
 *   label = @Translation("Ip constraint"),
 *   description = @Translation("Ip constraint.")
 * )
 */
class IpConstraintFieldValidationRule extends ConstraintFieldValidationRuleBase {

  /**
   * {@inheritdoc}
   */
  public function getConstraintName(): string{
    return "Ip";
  }

  /**
   * {@inheritdoc}
   */
  public function isPropertyConstraint(): bool{
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'version' => NULL,
      'message' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    //copied from core.
    $message = 'This is not a valid IP address.';

    $form['version'] = [
      '#title' => $this->t('Version'),
      '#type' => 'select',
      '#options' => [
        '4' => $this->t('V4'),
        '6' => $this->t('V6'),
        'all' => $this->t('ALL'),
        '4_no_priv' => $this->t('V4_NO_PRIV'),
        '6_no_priv' => $this->t('V6_NO_PRIV'),
        'all_no_priv' => $this->t('ALL_NO_PRIV'),
        '4_no_res' => $this->t('V4_NO_RES'),
        '6_no_res' => $this->t('V6_NO_RES'),
        'all_no_res' => $this->t('ALL_NO_RES'),
        '4_public' => $this->t('V4_ONLY_PUBLIC'),
        '6_public' => $this->t('V6_ONLY_PUBLIC'),
        'all_public' => $this->t('ALL_ONLY_PUBLIC'),
      ],
      '#default_value' => $this->configuration['version'],
    ];

    $form['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message'),
      '#default_value' => $this->configuration['message'] ?? $message,
      '#maxlength' => 255,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['version'] = $form_state->getValue('version');
    $this->configuration['message'] = $form_state->getValue('message');
  }

}
