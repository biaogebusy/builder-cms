<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConstraintFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * Provides funcationality for CidrConstraintFieldValidationRule.
 *
 * @FieldValidationRule(
 *   id = "cidr_constraint_rule",
 *   label = @Translation("Cidr constraint"),
 *   description = @Translation("Cidr constraint.")
 * )
 */
class CidrConstraintFieldValidationRule extends ConstraintFieldValidationRuleBase {

  /**
   * {@inheritdoc}
   */
  public function getConstraintName(): string{
    return "Cidr";
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
      'netmaskRangeViolationMessage' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    //copied from core.
    $message = 'This value is not a valid CIDR notation.';
    $netmaskRangeViolationMessage = 'The value of the netmask should be between %min and %max.';

    $form['version'] = [
      '#title' => $this->t('Version'),
      '#type' => 'select',
      '#options' => [
        '4' => $this->t('V4'),
        '6' => $this->t('V6'),
        'all' => $this->t('ALL'),
      ],
      '#default_value' => $this->configuration['version'],
    ];

    $form['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message'),
      '#default_value' => $this->configuration['message'] ?? $message,
      '#maxlength' => 255,
    ];

    $form['netmaskRangeViolationMessage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Netmask range violation message'),
      '#default_value' => $this->configuration['netmaskRangeViolationMessage'] ?? $netmaskRangeViolationMessage,
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
    $this->configuration['netmaskRangeViolationMessage'] = $form_state->getValue('netmaskRangeViolationMessage');	
  }

}
