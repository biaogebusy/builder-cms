<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConstraintFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * Provides funcationality for IssnConstraintFieldValidationRule.
 *
 * @FieldValidationRule(
 *   id = "issn_constraint_rule",
 *   label = @Translation("Issn constraint"),
 *   description = @Translation("Issn constraint.")
 * )
 */
class IssnConstraintFieldValidationRule extends ConstraintFieldValidationRuleBase {

  /**
   * {@inheritdoc}
   */
  public function getConstraintName(): string{
    return "Issn";
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
      'caseSensitive' => FALSE,
      'requireHyphen' => FALSE,
      'message' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    //copied from core.
    $message = 'This value is not a valid ISSN.';

    $form['caseSensitive'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Case Sensitive'),
      '#default_value' => $this->configuration['caseSensitive'],
    ];

    $form['requireHyphen'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require Hyphen'),
      '#default_value' => $this->configuration['requireHyphen'],
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

    $this->configuration['caseSensitive'] = $form_state->getValue('caseSensitive');
    $this->configuration['requireHyphen'] = $form_state->getValue('requireHyphen');
    $this->configuration['message'] = $form_state->getValue('message');
  }

}
