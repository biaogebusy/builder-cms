<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConstraintFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * Provides funcationality for CssColorConstraintFieldValidationRule.
 *
 * @FieldValidationRule(
 *   id = "css_color_constraint_rule",
 *   label = @Translation("CssColor constraint"),
 *   description = @Translation("CssColor constraint.")
 * )
 */
class CssColorConstraintFieldValidationRule extends ConstraintFieldValidationRuleBase {

  /**
   * {@inheritdoc}
   */
  public function getConstraintName(): string{
    return "CssColor";
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
      'formats' => [],
      'message' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    //copied from core.
    $message = 'This value is not a valid CSS color.';

    $formats_options = [
       "hex_long" => $this->t('Hex long'),
       "hex_long_with_alpha" => $this->t('Hex long with alpha'),
       "hex_short" => $this->t('Hex short'),
       "hex_short_with_alpha" => $this->t('Hex short with alpha'),
       "basic_named_colors" => $this->t('Basic named colors'),
       "extended_named_colors" => $this->t('Extended named colors'),
       "system_colors" => $this->t('System colors'),
       "keywords" => $this->t('Keywords'),
       "rgb" => $this->t('RGB'),
       "rgba" => $this->t('RGBA'),
       "hsl" => $this->t('HSL'),
       "hsla" => $this->t('HSLA'),
    ];

    $form['formats'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Formats'),
      '#options' => $formats_options,	  
      '#default_value' => $this->configuration['formats'],
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

    $this->configuration['formats'] = array_keys(array_filter($form_state->getValue('formats')));
    $this->configuration['message'] = $form_state->getValue('message');
  }

}
