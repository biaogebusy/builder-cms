<?php

namespace Drupal\field_validation\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\field_validation\FieldValidationRuleManager;
use Drupal\field_validation\FieldValidationRuleSetInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Plugin\CachedDiscoveryClearerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides an add form for field validation rule.
 */
class FieldValidationRuleAddForm extends FieldValidationRuleFormBase {

  /**
   * The fieldValidationRule manager.
   *
   * @var \Drupal\field_validation\FieldValidationRuleManager
   */
  protected $fieldValidationRuleManager;

  /**
   * Constructs a new FieldValidationRuleAddForm.
   *
   * @param \Drupal\field_validation\FieldValidationRuleManager $field_validation_rule_manager
   *   The fieldValidationRule manager.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, CachedDiscoveryClearerInterface $plugin_cache_clearer, ModuleHandlerInterface $module_handler, FieldValidationRuleManager $field_validation_rule_manager) {
    $this->entityFieldManager = $entity_field_manager;
    $this->pluginCacheClearer = $plugin_cache_clearer;
    $this->moduleHandler = $module_handler;	
    $this->fieldValidationRuleManager = $field_validation_rule_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('plugin.cache_clearer'),
      $container->get('module_handler'),
      $container->get('plugin.manager.field_validation.field_validation_rule')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, FieldValidationRuleSetInterface $field_validation_rule_set = NULL, $field_validation_rule = NULL, $field_name = '') {
    $form = parent::buildForm($form, $form_state, $field_validation_rule_set, $field_validation_rule);

    $form['#title'] = $this->t('Add %label field validation rule', ['%label' => $this->fieldValidationRule->label()]);
    $form['actions']['submit']['#value'] = $this->t('Add Rule');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareFieldValidationRule($field_validation_rule) {
    $field_validation_rule = $this->fieldValidationRuleManager->createInstance($field_validation_rule);
    // Set the initial weight so this field_validation_rule comes last.
    $field_validation_rule->setWeight(count($this->fieldValidationRuleSet->getFieldValidationRules()));
    return $field_validation_rule;
  }

}
