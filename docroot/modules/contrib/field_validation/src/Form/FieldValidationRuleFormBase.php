<?php

namespace Drupal\field_validation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field_validation\ConfigurableFieldValidationRuleInterface;
use Drupal\field_validation\FieldValidationRuleSetInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Plugin\CachedDiscoveryClearerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides a base form for FieldValidationRule.
 */
abstract class FieldValidationRuleFormBase extends FormBase {

  /**
   * The fieldValidationRuleSet.
   *
   * @var \Drupal\field_validation\FieldValidationRuleSetInterface
   */
  protected $fieldValidationRuleSet;

  /**
   * The fieldValidationRule.
   *
   * @var \Drupal\field_validation\FieldValidationRuleInterface
   */
  protected $fieldValidationRule;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The plugin cache clearer.
   *
   * @var \Drupal\Core\Plugin\CachedDiscoveryClearerInterface
   */
  protected $pluginCacheClearer;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new FieldValidationRuleForm.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Plugin\CachedDiscoveryClearerInterface $plugin_cache_clearer
   *   The plugin cache clearer.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, CachedDiscoveryClearerInterface $plugin_cache_clearer, ModuleHandlerInterface $module_handler) {
    $this->entityFieldManager = $entity_field_manager;
    $this->pluginCacheClearer = $plugin_cache_clearer;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('plugin.cache_clearer'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'field_validation_rule_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\field_validation\FieldValidationRuleSetInterface $field_validation_rule_set
   *   The field_validation_rule_set.
   * @param string $field_validation_rule
   *   The field_validation_rule ID.
   * @param string $field_name
   *   The field name.
   *
   * @return array
   *   The form structure.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   */
  public function buildForm(array $form, FormStateInterface $form_state, FieldValidationRuleSetInterface $field_validation_rule_set = NULL, $field_validation_rule = NULL, $field_name = '') {
    $this->fieldValidationRuleSet = $field_validation_rule_set;
    try {
      $this->fieldValidationRule = $this->prepareFieldValidationRule($field_validation_rule);
    }
    catch (PluginNotFoundException $e) {
      throw new NotFoundHttpException("Invalid field_validation_rule id: '$field_validation_rule'.");
    }
    $request = $this->getRequest();

    if (!($this->fieldValidationRule instanceof ConfigurableFieldValidationRuleInterface)) {
      throw new NotFoundHttpException();
    }

    // $form['#attached']['library'][] = 'field_validation/admin';
    $form['uuid'] = [
      '#type' => 'hidden',
      '#value' => $this->fieldValidationRule->getUuid(),
    ];
    $form['id'] = [
      '#type' => 'hidden',
      '#value' => $this->fieldValidationRule->getPluginId(),
    ];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field Validation Rule title'),
      '#default_value' => $this->fieldValidationRule->getTitle(),
      '#required' => TRUE,
    ];
    $entity_type_id = $this->fieldValidationRuleSet->getAttachedEntityType();
    $bundle = $this->fieldValidationRuleSet->getAttachedBundle();
    // $field_options = array();
    $field_options = [
      '' => $this->t('- Select -'),
    ];

    $baseFieldDefinitions = $this->entityFieldManager->getBaseFieldDefinitions($entity_type_id);
    foreach ($baseFieldDefinitions as $base_field_name => $base_field_definition) {
      $field_options[$base_field_name] = $base_field_definition->getLabel();
    }

    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions($entity_type_id, $bundle);
    foreach ($fieldDefinitions as $fieldname => $field_definition) {
      if (!empty($field_definition->getTargetBundle())) {
        $field_options[$fieldname] = $field_definition->getLabel();
      }
    }
    $default_field_name = $this->fieldValidationRule->getFieldName();
    if (!empty($field_name)) {
      $default_field_name = $field_name;
    }
    $user_input = $form_state->getUserInput();
    $default_field_name = $user_input['field_name'] ?? $default_field_name;

    $form['field_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Field name'),
      '#options' => $field_options,
      '#default_value' => $default_field_name,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'updateColumn'],
        'wrapper' => 'edit-field-name-wrapper',
        'event' => 'change',
      ],
    ];

    $default_column = $this->fieldValidationRule->getColumn();
    $default_column = $user_input['column'] ?? $default_column;
    $column_options = $this->findColumn($default_field_name);
    if (!in_array($default_column, $column_options)) {
      $default_column = "";
    }

    $form['column'] = [
      '#type' => 'select',
      '#title' => $this->t('Column of field'),
      '#options' => $column_options,
      '#default_value' => $default_column,
      '#required' => TRUE,
      '#prefix' => '<div id="edit-field-name-wrapper">',
      '#suffix' => '</div>',
      '#validated' => TRUE,
    ];
    $form['data'] = $this->fieldValidationRule->buildConfigurationForm([], $form_state);
    $form['error_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Error message'),
      '#default_value' => $this->fieldValidationRule->getErrorMessage(),
      '#required' => TRUE,
      '#maxlength' => 255,
    ];
    $form['data']['#tree'] = TRUE;
    // Add a token link.
    if ($this->moduleHandler->moduleExists('token')) {
      //Some entity not use entity_type_id as token type, we need change it.
      switch ($entity_type_id) {
        case 'taxonomy_term':
          $entity_type_id = str_replace('taxonomy_', '', $entity_type_id);
          break;
      }
      // Show the token help link.
      $form['pattern_container']['token_help'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => [$entity_type_id],
      ];
    }
	  
    // Check the URL for a weight, then the fieldValidationRule
    // otherwise use default.
    $form['weight'] = [
      '#type' => 'hidden',
      '#value' => $request->query->has('weight') ? (int) $request->query->get('weight') : $this->fieldValidationRule->getWeight(),
    ];

    $test_roles = $this->fieldValidationRule->getApplicableRoles();
    $form['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Apply to this roles'),
      '#default_value' => $test_roles,
      '#options' => array_map('\Drupal\Component\Utility\Html::escape', user_role_names()),
      '#description' => $this->t('If you select no roles, the rule will be applicable for all users.'),
    ];

    $condition = $this->fieldValidationRule->getCondition();
    $form['condition'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#tree' => TRUE,
      '#title' => $this->t('Condition'),
    ];

    $form['condition']['field'] = [
      '#type' => 'select',
      '#title' => $this->t('Field name'),
      '#options' => $field_options,
      '#default_value' => $condition['field'] ?? "",
    ];

    $operator_options = [
      '' => $this->t('- Select -'),
      'equals' => $this->t('Equals'),
      'not_equals' => $this->t('Not equals'),
      'greater_than' => $this->t('Greater than'),
      'less_than' => $this->t('Less than'),
      'greater_or_equal' => $this->t('Greater or equal'),
      'less_or_equal' => $this->t('Less or equal'),
      'empty' => $this->t('Empty'),
      'not_empty' => $this->t('Not empty'),
    ];
    $form['condition']['operator'] = [
      '#type' => 'select',
      '#title' => $this->t('Operator'),
      '#options' => $operator_options,
      '#default_value' => $condition['operator'] ?? "",
    ];

    $form['condition']['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#default_value' => $condition['value'] ?? "",
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $this->fieldValidationRuleSet->toUrl('edit-form'),
      '#attributes' => ['class' => ['button']],
    ];
    return $form;
  }

  /**
   * Handles switching the configuration type selector.
   */
  public function updateColumn($form, FormStateInterface $form_state) {
    return $form['column'];
  }

  /**
   * Handles switching the configuration type selector.
   */
  protected function findColumn($field_name) {
    $column_options = [
      '' => $this->t('- Select -'),
    ];
    if (empty($field_name)) {
      return $column_options;
    }
    $entity_type_id = $this->fieldValidationRuleSet->getAttachedEntityType();
    $baseFieldDefinitions = $this->entityFieldManager->getBaseFieldDefinitions($entity_type_id);
    $schema = [];
    if (isset($baseFieldDefinitions[$field_name])) {
      $field_info = $baseFieldDefinitions[$field_name];
      $schema = $field_info->getSchema();
    }
    else {
      $field_info = FieldStorageConfig::loadByName($entity_type_id, $field_name);
      $schema = $field_info->getSchema();
    }

    if (!empty($schema['columns'])) {
      $columns = $schema['columns'];
      foreach ($columns as $key => $value) {
        $column_options[$key] = $key;
      }
    }
    return $column_options;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // The fieldValidationRule configuration is stored
    // in the 'data' key in the form,
    // pass that through for validation.
    $data = $form_state->getValue('data');
    if (empty($data)) {
      $data = [];
    }
    $field_validation_rule_data = (new FormState())->setValues($data);
    $this->fieldValidationRule->validateConfigurationForm($form, $field_validation_rule_data);
    // Update the original form values.
    $form_state->setValue('data', $field_validation_rule_data->getValues());
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();
    // drupal_flush_all_caches();
    // Clear all plugin caches.
    $this->pluginCacheClearer->clearCachedDefinitions();

    // The fieldValidationRule configuration is stored
    // in the 'data' key in the form,
    // pass that through for submission.
    $field_validation_rule_data = (new FormState())->setValues($form_state->getValue('data'));
    $this->fieldValidationRule->submitConfigurationForm($form, $field_validation_rule_data);

    // Update the original form values.
    $form_state->setValue('data', $field_validation_rule_data->getValues());
    $this->fieldValidationRule->setTitle($form_state->getValue('title'));
    $this->fieldValidationRule->setWeight($form_state->getValue('weight'));
    $this->fieldValidationRule->setFieldName($form_state->getValue('field_name'));
    $this->fieldValidationRule->setColumn($form_state->getValue('column'));
    $this->fieldValidationRule->setErrorMessage($form_state->getValue('error_message'));
    // Update the rule applicable roles.
    $this->fieldValidationRule->setApplicableRoles(array_filter($form_state->getValue('roles')));
    $this->fieldValidationRule->setCondition($form_state->getValue('condition'));
    if (!$this->fieldValidationRule->getUuid()) {
      $this->fieldValidationRuleSet->addFieldValidationRule($this->fieldValidationRule->getConfiguration());
    }
    else {
      $this->fieldValidationRuleSet->deleteFieldValidationRule($this->fieldValidationRule);
      $this->fieldValidationRuleSet->addFieldValidationRule($this->fieldValidationRule->getConfiguration());
    }
    $this->fieldValidationRuleSet->save();
    $this->messenger()->addMessage($this->t('The rule was successfully applied.'));
    $form_state->setRedirectUrl($this->fieldValidationRuleSet->toUrl('edit-form'));
  }

  /**
   * Converts a field_validation_rule ID into an object.
   *
   * @param string $field_validation_rule
   *   The field_validation_rule ID.
   *
   * @return \Drupal\field_validation\FieldValidationRuleInterface
   *   The field_validation_rule object.
   */
  abstract protected function prepareFieldValidationRule($field_validation_rule);

}
