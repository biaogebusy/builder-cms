<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConfigurableFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * Provides functaionality for BlocklistFieldValidationRule.
 *
 * @FieldValidationRule(
 *   id = "blocklist_field_validation_rule",
 *   label = @Translation("Words blocklist"),
 *   description = @Translation("Validates that user-entered data doesn't contain any of the specified illegal words.")
 * )
 */
class BlocklistFieldValidationRule extends ConfigurableFieldValidationRuleBase {

  /**
   * {@inheritdoc}
   */
  public function addFieldValidationRule(FieldValidationRuleSetInterface $field_validation_rule_set) {

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $summary = parent::getSummary();

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'setting' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['setting'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Blocklisted words'),
      '#description' => $this->t('Specify illegal words, seperated by commas. Make sure to escape reserved regex characters with an escape (\) character.'),
      '#default_value' => $this->configuration['setting'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['setting'] = $form_state->getValue('setting');
  }

  /**
   * {@inheritdoc}
   */
  public function validate($params) {
    $value = $params['value'] ?? '';
    $rule = $params['rule'] ?? NULL;
    $context = $params['context'] ?? NULL;
    $settings = [];
    if (!empty($rule) && !empty($rule->configuration)) {
      $settings = $rule->configuration;
    }
    $setting = $settings['setting'] ?? '';
    $blocklist = explode(',', $setting);
    $blocklist = array_map('trim', $blocklist);
    $blocklist_regex = implode('|', $blocklist);
    // $settings = $this->rule->settings;
    if ($value !== '' && !is_null($value) && preg_match("/$blocklist_regex/i", $value)) {
      $context->addViolation($rule->getReplacedErrorMessage($params));
    }
  }

}
