<?php

namespace Drupal\field_validation\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\ConfigurableFieldValidationRuleBase;
use Drupal\field_validation\FieldValidationRuleSetInterface;

/**
 * Unique Field Validation Rule.
 *
 * @FieldValidationRule(
 *   id = "unique_field_validation_rule",
 *   label = @Translation("Unique"),
 *   description = @Translation("Verifies that all values are unique in current entity or bundle.")
 * )
 */
class UniqueFieldValidationRule extends ConfigurableFieldValidationRuleBase {

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
      'scope' => NULL,
      'per_user' => FALSE,
      'published' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['scope'] = [
      '#title' => $this->t('Scope of unique'),
      '#description' => $this->t('Specify the scope of unique values, support: entity, bundle.'),
      '#type' => 'select',
      '#options' => [
        'entity' => $this->t('Entity'),
        'bundle' => $this->t('Bundle'),
      ],
      '#default_value' => $this->configuration['scope'],
    ];

    $rule_set = $form_state->getBuildInfo()['args'][0];
    $entity_type_id = $rule_set->getAttachedEntityType();
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id, FALSE);

    if ($entity_type->getKey('published')) {
      $form['published'] = [
        '#title' => $this->t('Only for published entities'),
        '#type' => 'checkbox',
        '#default_value' => $this->configuration['published'] ?: FALSE,
      ];
    }

    if ($entity_type->getKey('owner')) {
      $form['per_user'] = [
        '#title' => $this->t('Per user'),
        '#type' => 'checkbox',
        '#default_value' => $this->configuration['per_user'] ?: FALSE,
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['scope'] = $form_state->getValue('scope');
    $this->configuration['published'] = $form_state->getValue('published') ?: FALSE;
    $this->configuration['per_user'] = $form_state->getValue('per_user') ?: FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($params) {
    $value = $params['value'] ?? '';
    $rule = $params['rule'] ?? NULL;
    $context = $params['context'] ?? NULL;
    $items = $params['items'] ?? [];
    $delta = $params['delta'] ?? '';
    $column = $rule->getColumn();

    $settings = [];
    if (!empty($rule) && !empty($rule->configuration)) {
      $settings = $rule->configuration;
    }
    $flag = TRUE;
    $scope = $settings['scope'] ?? '';
    $published = $settings['published'] ?? FALSE;
    $per_user = $settings['per_user'] ?? FALSE;
    $count = 0;
    foreach ($items as $delta1 => $item1) {
      if ($delta != $delta1) {
        if ($value == $item1->{$column}) {
          $flag = FALSE;
          break;
        }
      }
    }
    if ($flag) {
      $entity = $items->getEntity();
      $entity_type_id = $entity->getEntityTypeId();

      $query = \Drupal::entityQuery($entity_type_id);
      $query->addTag('field_validation');
      $query->accessCheck(FALSE);

      if ($scope == 'bundle') {
        $bundle = $entity->bundle();
        $bundle_key = $entity->getEntityType()->getKey('bundle');
        if (!empty($bundle_key)) {
          $query->condition($bundle_key, $bundle);
        }
      }

      if ($published) {
        $published_key = $entity->getEntityType()->getKey('published');
        if (!empty($published_key)) {
          $query->condition($published_key, 1);
        }
      }

      if ($per_user) {
        $owner_key = $entity->getEntityType()->getKey('owner');
        if (!empty($owner_key)) {
          $query->condition($owner_key, \Drupal::currentUser()->id());
        }
      }

      $id_key = $entity->getEntityType()->getKey('id');
      $query->condition($id_key, (int) $items->getEntity()->id(), '<>');

      $field_name = $items->getFieldDefinition()->getName();

      if (!empty($column)) {
        $field_name = $field_name . '.' . $column;
      }
      $query->condition($field_name, $value);

      $count = $query->range(0, 1)
        ->count()
        ->execute();

      if ($count) {
        $flag = FALSE;

      }
    }

    if (!$flag) {
      $context->addViolation($rule->getReplacedErrorMessage($params));
    }
  }

}
