<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConstraintFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * Provides funcationality for CardSchemeConstraintFieldValidationRule.
 *
 * @FieldValidationRule(
 *   id = "card_scheme_constraint_rule",
 *   label = @Translation("CardScheme constraint"),
 *   description = @Translation("CardScheme constraint.")
 * )
 */
class CardSchemeConstraintFieldValidationRule extends ConstraintFieldValidationRuleBase {

  /**
   * {@inheritdoc}
   */
  public function getConstraintName(): string{
    return "CardScheme";
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
      'schemes' => [],
      'message' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    //copied from core.
    $message = 'Unsupported card type or invalid card number.';

    $schemes_options = [
       "AMEX" => $this->t('AMEX'),
       "CHINA_UNIONPAY" => $this->t('CHINA UNIONPAY'),
       "DINERS" => $this->t('DINERS'),
       "DISCOVER" => $this->t('DISCOVER'),
       "INSTAPAYMENT" => $this->t('INSTAPAYMENT'),
       "JCB" => $this->t('JCB'),
       "LASER" => $this->t('LASER'),
       "MAESTRO" => $this->t('MAESTRO'),
       "MASTERCARD" => $this->t('MASTERCARD'),
       "MIR" => $this->t('MIR'),
       "UATP" => $this->t('UATP'),
       "VISA" => $this->t('VISA'),
    ];

    $form['schemes'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Schemes'),
      '#options' => $schemes_options,	  
      '#default_value' => $this->configuration['schemes'],
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

    $this->configuration['schemes'] = array_keys(array_filter($form_state->getValue('schemes')));
    $this->configuration['message'] = $form_state->getValue('message');
  }

}
