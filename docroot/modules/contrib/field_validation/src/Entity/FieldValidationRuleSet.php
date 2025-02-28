<?php

namespace Drupal\field_validation\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\field_validation\FieldValidationRuleSetInterface;
use Drupal\field_validation\FieldValidationRuleInterface;
use Drupal\field_validation\FieldValidationRulePluginCollection;

/**
 * Defines a field validation rule set configuration entity.
 *
 * @ConfigEntityType(
 *   id = "field_validation_rule_set",
 *   label = @Translation("Field validation rule set"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\field_validation\Form\FieldValidationRuleSetAddForm",
 *       "edit" = "Drupal\field_validation\Form\FieldValidationRuleSetEditForm",
 *       "delete" = "Drupal\field_validation\Form\FieldValidationRuleSetDeleteForm",
 *     },
 *     "list_builder" = "Drupal\field_validation\FieldValidationRuleSetListBuilder",
 *   },
 *   admin_permission = "administer field validation rule set",
 *   config_prefix = "rule_set",
 *   entity_keys = {
 *     "id" = "name",
 *     "label" = "label"
 *   },
 *   links = {
 *     "edit-form" = "/admin/structure/field_validation/manage/{field_validation_rule_set}",
 *     "delete-form" = "/admin/structure/field_validation/manage/{field_validation_rule_set}/delete",
 *     "collection" = "/admin/structure/field_validation",
 *   },
 *   config_export = {
 *     "name",
 *     "label",
 *     "entity_type",
 *     "bundle",
 *     "field_validation_rules",
 *   }
 * )
 */
class FieldValidationRuleSet extends ConfigEntityBase implements FieldValidationRuleSetInterface, EntityWithPluginCollectionInterface {


  /**
   * The name of the FieldValidationRuleSet.
   *
   * @var string
   */
  protected $name;

  /**
   * The FieldValidationRuleSet label.
   *
   * @var string
   */
  protected $label;

  /**
   * The entity type of FieldValidationRuleSet.
   *
   * @var string
   */
  protected $entity_type;

  /**
   * The bundle of FieldValidationRuleSet.
   *
   * @var string
   */
  protected $bundle;

  /**
   * The array of fieldValidationRules for this FieldValidationRuleSet.
   *
   * @var array
   */
  protected $field_validation_rules = [];

  /**
   * Holds the collection of fieldValidationRules that are used by this FieldValidationRuleSet.
   *
   * @var \Drupal\field_validation\FieldValidationRulePluginCollection
   */
  protected $fieldValidationRulesCollection;

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteFieldValidationRule(FieldValidationRuleInterface $field_validation_rule) {
    $this->getFieldValidationRules()->removeInstanceId($field_validation_rule->getUuid());
    $this->save();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldValidationRule($field_validation_rule) {
    return $this->getFieldValidationRules()->get($field_validation_rule);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldValidationRules() {
    if (!$this->fieldValidationRulesCollection) {
      $this->fieldValidationRulesCollection = new FieldValidationRulePluginCollection($this->getFieldValidationRulePluginManager(), $this->field_validation_rules);
      $this->fieldValidationRulesCollection->sort();
    }
    return $this->fieldValidationRulesCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return ['field_validation_rules' => $this->getFieldValidationRules()];
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValidationRule(array $configuration) {
    $configuration['uuid'] = $this->uuidGenerator()->generate();
    $this->getFieldValidationRules()->addInstanceId($configuration['uuid'], $configuration);
    return $configuration['uuid'];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name');
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * Returns the FieldValidationRule plugin manager.
   *
   * @return \Drupal\Component\Plugin\PluginManagerInterface
   *   The FieldValidationRule plugin manager.
   */
  protected function getFieldValidationRulePluginManager() {
    return \Drupal::service('plugin.manager.field_validation.field_validation_rule');
  }

  /**
   * {@inheritdoc}
   */
  public function getAttachedEntityType() {
    return $this->get('entity_type');
  }

  /**
   * {@inheritdoc}
   */
  public function setAttachedEntityType($entity_type) {
    $this->set('entity_type', $entity_type);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAttachedBundle() {
    return $this->get('bundle');
  }

  /**
   * {@inheritdoc}
   */
  public function setAttachedBundle($bundle) {
    $this->set('bundle', $bundle);
    return $this;
  }

}
