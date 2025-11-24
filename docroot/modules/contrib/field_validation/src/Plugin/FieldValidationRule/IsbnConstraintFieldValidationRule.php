<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConstraintFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * Provides funcationality for IsbnConstraintFieldValidationRule.
 *
 * @FieldValidationRule(
 *   id = "isbn_constraint_rule",
 *   label = @Translation("Isbn constraint"),
 *   description = @Translation("Isbn constraint.")
 * )
 */
class IsbnConstraintFieldValidationRule extends ConstraintFieldValidationRuleBase {

  /**
   * {@inheritdoc}
   */
  public function getConstraintName(): string{
    return "Isbn";
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
      'type' => "",
      'message' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    //copied from core.
    $message = 'This value is not a valid ISBN.';

    $type_options = [
      "" => $this->t('-Select-'),
      "isbn10" => $this->t('ISBN 10'),
      "isbn13" => $this->t('ISBN 13'),
    ];

    $form['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => $type_options,	  
      '#default_value' => $this->configuration['type'],
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

    $this->configuration['type'] = $form_state->getValue('type');
    $this->configuration['message'] = $form_state->getValue('message');
  }

}
