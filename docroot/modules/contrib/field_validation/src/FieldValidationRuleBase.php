<?php

namespace Drupal\field_validation;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Component\Plugin\Factory\DefaultFactory;
use Drupal\field\Entity\FieldStorageConfig;
use \Drupal\Core\Utility\Token;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for FieldValidationRule.
 *
 * @see \Drupal\field_validation\Annotation\FieldValidationRule
 * @see \Drupal\field_validation\FieldValidationRuleInterface
 * @see \Drupal\field_validation\ConfigurableFieldValidationRuleInterface
 * @see \Drupal\field_validation\ConfigurableFieldValidationRuleBase
 * @see \Drupal\field_validation\FieldValidationRuleManager
 * @see plugin_api
 */
abstract class FieldValidationRuleBase extends PluginBase implements FieldValidationRuleInterface, ContainerFactoryPluginInterface {

  /**
   * The FieldValidationRule ID.
   *
   * @var string
   */
  protected $uuid;

  /**
   * The weight of the FieldValidationRule.
   *
   * @var int|string
   */
  protected $weight = '';

  /**
   * The title of the FieldValidationRule.
   *
   * @var string
   */
  protected $title = '';

  /**
   * The field name of the FieldValidationRule.
   *
   * @var string
   */
  protected $field_name = '';

  /**
   * The column of the FieldValidationRule.
   *
   * @var string
   */
  protected $column = '';

  /**
   * The error message of the FieldValidationRule.
   *
   * @var string
   */
  protected $error_message = '';

  /**
   * The user roles to which this rule is applicable.
   *
   * @var string[]
   */
  protected $roles = [];

  /**
   * The field condition to which this rule is applicable.
   *
   * @var array
   */
  protected $condition = [];

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $tokenService;  

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, Token $token_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->setConfiguration($configuration);
    $this->tokenService = $token_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('field_validation'),
	  $container->get('token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeExtension($extension) {
    // Most tabs will not change the extension. This base
    // implementation represents this behavior. Override this method if your
    // tab does change the extension.
    return $extension;
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    return [
      '#markup' => '',
      '#tab' => [
        'id' => $this->pluginDefinition['id'],
        'label' => $this->label(),
        'description' => $this->pluginDefinition['description'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->title;
  }

  /**
   * {@inheritdoc}
   */
  public function getUuid() {
    return $this->uuid;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->weight = $weight;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->title;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return [
      'uuid' => $this->getUuid(),
      'id' => $this->getPluginId(),
      'title' => $this->getTitle(),
      'weight' => $this->getWeight(),
      'field_name' => $this->getFieldName(),
      'column' => $this->getColumn(),
      'error_message' => $this->getErrorMessage(),
      'roles' => $this->getApplicableRoles(),
      'condition' => $this->getCondition(),
      'data' => $this->configuration,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $configuration += [
      'data' => [],
      'uuid' => '',
      'title' => '',
      'weight' => '',
      'field_name' => '',
      'column' => '',
      'error_message' => '',
      'roles' => [],
      'condition' => [],
    ];
    $this->configuration = $configuration['data'] + $this->defaultConfiguration();
    $this->uuid = $configuration['uuid'];
    $this->title = $configuration['title'];
    $this->weight = $configuration['weight'];
    $this->field_name = $configuration['field_name'];
    $this->column = $configuration['column'];
    $this->error_message = $configuration['error_message'];
    $this->roles = $configuration['roles'];
    $this->condition = $configuration['condition'];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * Returns the field name of the field_validation_rule.
   *
   * @return string
   *   The field name of the field_validation_rule.
   */
  public function getFieldName() {
    return $this->field_name;
  }

  /**
   * Sets the field name for this field_validation_rule.
   *
   * @param int $field_name
   *   The field name for this field_validation_rule.
   *
   * @return $this
   */
  public function setFieldName($field_name) {
    $this->field_name = $field_name;
    return $this;
  }

  /**
   * Returns the column of the field_validation_rule.
   *
   * @return string
   *   The column of the field_validation_rule.
   */
  public function getColumn() {
    return $this->column;
  }

  /**
   * Sets the column for this field_validation_rule.
   *
   * @param int $column
   *   The column for this field_validation_rule.
   *
   * @return $this
   */
  public function setColumn($column) {
    $this->column = $column;
    return $this;
  }

  /**
   * Returns the error message of the field_validation_rule.
   *
   * @return string
   *   The error message of the field_validation_rule.
   */
  public function getErrorMessage() {
    return $this->error_message;
  }

  /**
   * Sets the error message for this field_validation_rule.
   *
   * @param int $error_message
   *   The error message for this field_validation_rule.
   *
   * @return $this
   */
  public function setErrorMessage($error_message) {
    $this->error_message = $error_message;
    return $this;
  }

  /**
   * Returns the replaced error message with token.
   *
   * @return string
   *   The error message to display.
   */
  public function getReplacedErrorMessage(array $params) {
    $error_message = $this->error_message;

    $data = $this->getTokenData($params);
    if (empty($data)) {
      return $error_message;
    }

    $error_message = $this->tokenService->replace($error_message, $data);

    return $error_message;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($params) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getApplicableRoles() {
    return $this->roles;
  }

  /**
   * {@inheritdoc}
   */
  public function setApplicableRoles(array $roles) {
    $this->roles = $roles;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCondition() {
    return $this->condition;
  }

  /**
   * {@inheritdoc}
   */
  public function setCondition(array $condition) {
    $this->condition = $condition;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function checkCondition(ContentEntityInterface $entity) {
    $condition = $this->condition;
    $field_name = $condition['field'] ?? "";
    $operator = $condition['operator'] ?? "";
    if (empty($field_name) || empty($operator)) {
      return TRUE;
    }
    $value = $condition['value'] ?? "";
	

	$entity_type = $entity->getEntityTypeId();
    //Some entity not use entity_type_id as token type, we need change it.
    switch ($entity_type) {
      case 'taxonomy_term':
        $entity_type = str_replace('taxonomy_', '', $entity_type);
        break;
    }
    $token_data = [$entity_type => $entity];
    $value = $this->tokenService->replace($value, $token_data);
    // \Drupal::messenger()->addMessage("field_name:" .var_export($field_name,true));
    //$entity_type_id = $entity->getEntityType()->id();
    $field_type =  $entity->getFieldDefinition($field_name)->getType();
    $field_type_manager =  \Drupal::service('plugin.manager.field.field_type');
    $plugin_definition = $field_type_manager->getDefinition($field_type, FALSE);
    //Get main property, default value.
    $main_property = "value";
    if (!empty($plugin_definition['class'])) {
      $plugin_class = DefaultFactory::getPluginClass($field_type, $plugin_definition);
      $main_property = $plugin_class::mainPropertyName();
    } 

    $field_value = $entity->{$field_name}->{$main_property} ?? NULL;
    // \Drupal::messenger()->addMessage("field_value:" .var_export($field_value,true));

    //Type convert, do we need this code?
    // if (is_int($field_value)) {
    //  $value = (int) $value;
    // }elseif (is_float($field_value)) {
    //  $value = (float) $value;
    // }
    //  \Drupal::messenger()->addMessage("value:" .var_export($value,true));
    switch ($operator){
      case 'equals':
          return $field_value == $value;
          break;  
      case 'not_equals':
          return $field_value != $value;
          break;
      case 'greater_than':
          return $field_value > $value;
          break;  
      case 'less_than':
          return $field_value < $value;
          break;
      case 'greater_or_equal':
          return $field_value >= $value;
          break;  
      case 'less_or_equal':
          return $field_value <= $value;
          break;
      case 'empty':
          return empty($field_value);
          break;
      case 'not_empty':
          return !empty($field_value);
          break;		  
    }
	  
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getTokenData($params) {
    $ret = [];
    $items = $params['items'] ?? [];
    if (empty($items)) {
      return $ret;
    }

    // Get entity_type, do we need human name?
    $entity = $items->getEntity();
	$entity_type = $entity->getEntityTypeId();
    // $entity_type = $entity->getEntityType()->label();

    // Get bundle, do we need human name?
	$bundle = $entity->bundle();
    // $entity_type_id = $entity->getEntityTypeId();
    // $bundle_id = $entity->bundle();
    // $bundle_info = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity_type_id);
    // $bundle = $bundle_info[$bundle_id]['label'];

    // Get field name
    $field_name = $items->getFieldDefinition()->getLabel();
	$value = $params['value'] ?? "";

    $current_field = [
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'field_name' => $field_name,
      'value' => $value,
    ];

    //Some entity not use entity_type_id as token type, we need change it.
    switch ($entity_type) {
      case 'taxonomy_term':
        $entity_type = str_replace('taxonomy_', '', $entity_type);
        break;
    }

    $ret = [
      'current_field' => $current_field,
      $entity_type => $entity,
    ];	

    return $ret;
  }

}
