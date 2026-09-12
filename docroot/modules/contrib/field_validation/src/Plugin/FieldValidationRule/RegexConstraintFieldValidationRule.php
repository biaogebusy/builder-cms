<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConstraintFieldValidationRuleBase;

/**
 * Provides functionality for RegexConstraintFieldValidationRule.
 *
 * @FieldValidationRule(
 *   id = "regex_constraint_rule",
 *   label = @Translation("Regex constraint"),
 *   description = @Translation("Regex constraint.")
 * )
 */
class RegexConstraintFieldValidationRule extends ConstraintFieldValidationRuleBase {

  /**
   * {@inheritdoc}
   */
  public function getConstraintName(): string {
    return "Regex";
  }

  /**
   * {@inheritdoc}
   */
  public function isPropertyConstraint(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'pattern' => NULL,
      'message' => NULL,
      'match' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Copied from core.
    $message = 'This value is not valid.';

    $form['pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pattern'),
      '#description' => $this->t('A PCRE regular expression, including its delimiters, e.g. <code>/^[a-z0-9]+$/</code>. Unlike Drupal 7, the pattern must be wrapped in delimiters (commonly <code>/</code>); a bare expression such as <code>^[a-z0-9]+$</code> is not valid. By default the value is considered <strong>valid</strong> only if it matches this pattern; uncheck "Value must match the pattern to be valid" below to instead flag values that match the pattern as invalid.'),
      '#default_value' => $this->configuration['pattern'],
      '#required' => TRUE,
    ];

    $form['match'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Value must match the pattern to be valid'),
      '#description' => $this->t('Checked (default): the value is valid only if it matches the pattern. Unchecked: the value is valid only if it does NOT match the pattern, e.g. to flag values containing a forbidden substring or word.'),
      '#default_value' => $this->configuration['match'] ?? TRUE,
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

    $this->configuration['pattern'] = $form_state->getValue('pattern');
    $this->configuration['message'] = $form_state->getValue('message');
    $this->configuration['match'] = (bool) $form_state->getValue('match');
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $pattern = $form_state->getValue('pattern');
    if ($pattern === NULL || $pattern === '') {
      return;
    }

    // preg_match() emits a raw PHP warning (and returns FALSE) for a
    // malformed pattern, e.g. one missing its delimiters. Validate the
    // pattern here so a clean, guided form error is shown instead of that
    // warning leaking through when the rule actually runs. A custom error
    // handler is used instead of the "@" operator so the warning is caught
    // reliably regardless of the site's error handler configuration.
    $warning = NULL;
    set_error_handler(function (int $errno, string $errstr) use (&$warning): bool {
      $warning = $errstr;
      return TRUE;
    }, E_WARNING);
    try {
      $result = preg_match($pattern, '');
    }
    finally {
      restore_error_handler();
    }

    if ($result === FALSE || $warning !== NULL) {
      $form_state->setErrorByName('pattern', $this->t('This regular expression is not valid: @error. Make sure the pattern includes delimiters, e.g. <code>/^[a-z0-9]+$/</code>.', [
        '@error' => $warning ?? preg_last_error_msg(),
      ]));
    }
  }

}
