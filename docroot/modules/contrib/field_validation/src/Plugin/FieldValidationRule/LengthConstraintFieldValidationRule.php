<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConstraintFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * Provides funcationality for EmailFieldValidationRule.
 *
 * @FieldValidationRule(
 *   id = "length_constraint_rule",
 *   label = @Translation("Length constraint"),
 *   description = @Translation("Length constraint.")
 * )
 */
class LengthConstraintFieldValidationRule extends ConstraintFieldValidationRuleBase {

  /**
   * {@inheritdoc}
   */
  public function getConstraintName(): string{
    return "Length";
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
      'min' => NULL,
      'max' => NULL,
      'maxMessage' => NULL,
      'minMessage' => NULL,
      'exactMessage' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    //copied from core.
    $maxMessage = 'This value is too long. It should have %limit character or less.|This value is too long. It should have %limit characters or less.';
    $minMessage = 'This value is too short. It should have %limit character or more.|This value is too short. It should have %limit characters or more.';
    $exactMessage = 'This value should have exactly %limit character.|This value should have exactly %limit characters.';
	  
    $form['min'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Min'),
      '#default_value' => $this->configuration['min'],
      '#required' => TRUE,
    ];
    $form['max'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Max'),
      '#default_value' => $this->configuration['max'],
      '#required' => TRUE,
    ];
    $form['maxMessage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Max Message'),
      '#default_value' => $this->configuration['maxMessage'] ?? $maxMessage,
      '#maxlength' => 255,
    ];
    $form['minMessage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Min Message'),
      '#default_value' => $this->configuration['minMessage'] ?? $minMessage,
      '#maxlength' => 255,	  
    ];
    $form['exactMessage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Exact Message'),
      '#default_value' => $this->configuration['exactMessage'] ?? $exactMessage,
      '#maxlength' => 255,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['min'] = $form_state->getValue('min');
    $this->configuration['max'] = $form_state->getValue('max');
    $this->configuration['maxMessage'] = $form_state->getValue('maxMessage');
    $this->configuration['minMessage'] = $form_state->getValue('minMessage');
    $this->configuration['exactMessage'] = $form_state->getValue('exactMessage');
  }

}
