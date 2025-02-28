<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\field_validation\ConfigurableFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * Prodives the Url Field Validation Rule.
 *
 * @FieldValidationRule(
 *   id = "url_field_validation_rule",
 *   label = @Translation("URL"),
 *   description = @Translation("Verifies that user-entered data is a valid url.")
 * )
 */
class UrlFieldValidationRule extends ConfigurableFieldValidationRuleBase {

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
      'external' => FALSE,
      'internal' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['external'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('External URL'),
      '#description' => $this->t("Limit allowed input to absolute/external url."),
      '#default_value' => $this->configuration['external'],
    ];
    $form['internal'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Internal path'),
      '#description' => $this->t("Limit allowed input to internal drupal path."),
      '#default_value' => $this->configuration['internal'],
    ];
    $form['help'] = [
      '#markup' => $this->t("If both of External URL and Internal path are checked, it means that both of them are allowed."),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['external'] = $form_state->getValue('external');
    $this->configuration['internal'] = $form_state->getValue('internal');
  }

  /**
   * Validate the url.
   */
  public function validate($params) {
    $value = $params['value'] ?? '';
    $rule = $params['rule'] ?? NULL;
    $context = $params['context'] ?? NULL;
    $settings = [];
    if (!empty($rule) && !empty($rule->configuration)) {
      $settings = $rule->configuration;
    }

    if ($value != '') {
      $flag = FALSE;
      if (empty($settings['external']) && empty($settings['internal'])) {
        $flag = TRUE;
      }

      if (!empty($settings['external'])) {
        $flag = UrlHelper::isValid($value, TRUE);
      }

      if (!$flag && !empty($settings['internal'])) {
        $normal_path = \Drupal::service('path_alias.manager')->getPathByAlias($value);
        if (!UrlHelper::isExternal($normal_path)) {
          $parsed_link = UrlHelper::parse($normal_path);
          if ($normal_path != $parsed_link['path']) {
            $normal_path = $parsed_link['path'];
          }
          $flag = \Drupal::service('path.validator')->isValid($normal_path);
        }
      }

      if (!$flag) {
        $context->addViolation($rule->getReplacedErrorMessage($params));
      }
    }
  }

}
