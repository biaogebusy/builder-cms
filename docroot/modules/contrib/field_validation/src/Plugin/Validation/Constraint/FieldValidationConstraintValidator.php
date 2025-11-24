<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Drupal\field_validation\ConstraintFieldValidationRuleBase;
use Drupal\Core\Validation\ConstraintValidatorFactory;

/**
 * Validates the FieldValidation constraint.
 */
class FieldValidationConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    $ruleset_name = $constraint->ruleset_name;
    $ruleset = \Drupal::entityTypeManager()->getStorage('field_validation_rule_set')->load($ruleset_name);
    if (empty($ruleset)) {
      return;
    }

    // For base field validation, we limit it to attached bundle.
    $entity = $items->getEntity();
    $bundle = $entity->bundle();

    if ($bundle != $ruleset->getAttachedBundle()) {
      $ruleset_name = $entity->getEntityType()->id() . '_' . $bundle;
      $ruleset = \Drupal::entityTypeManager()
        ->getStorage('field_validation_rule_set')
        ->load($ruleset_name);
      if (empty($ruleset)) {
        return;
      }
    }

    $rules = $ruleset->getFieldValidationRules();
    $rules_available = [];
    $field_name = $items->getFieldDefinition()->getName();

    foreach ($rules as $rule) {
      if ($rule->getFieldName() == $field_name
      && (
        !($applicable_roles = $rule->getApplicableRoles())
        || array_intersect($applicable_roles, \Drupal::currentUser()->getRoles()))
      && (
        $rule->checkCondition($entity))
      ) {
        $rules_available[] = $rule;
      }
    }
    if (empty($rules_available)) {
      return;
    }

    //Divide them into 2 array, one for field, the other for property
    $rules_field = [];
    $rules_property = [];
    foreach ($rules_available as $rule) {
      $is_constraint_rule = ($rule instanceof ConstraintFieldValidationRuleBase);
      $validate_mode = $rule->getConfiguration()['data']['validate_mode'] ?? "default";
      // Remove "direct" mode rule.
      if ($validate_mode == "direct"){
        continue;
	  }
      if ($is_constraint_rule && $validate_mode =="default" && (!$rule->isPropertyConstraint())) {
        $rules_field[] = $rule;
      }else{
        $rules_property[] = $rule;
	  }
    }

    $field_validation_rule_manager = \Drupal::service('plugin.manager.field_validation.field_validation_rule');
    $constraint_manager = \Drupal::service('validation.constraint');
	$class_resolver  = \Drupal::service('class_resolver');
    $constraint_validator_factory =  new ConstraintValidatorFactory($class_resolver);

    $params = [];
    $params['items'] = $items;
    $params['context'] = $this->context;

    // Field level validation,
    foreach ($rules_field as $rule) {
      $constraint_name = $rule->getConstraintName();
      $constraint_options = $rule->getReplacedConstraintOptions($params);

      $real_constraint = $constraint_manager->createInstance($constraint_name, $constraint_options);
      $validator = $constraint_validator_factory->getInstance($real_constraint);
      $validator->initialize($this->context);
      $validator->validate($items, $real_constraint);				
    }

    // Property level validation
    if ($items->count() !== 0) {
      foreach ($items as $delta => $item) {
        // You can hard code configuration or you load from settings.
        foreach ($rules_property as $rule) {
          $column = $rule->getColumn();
          $value = $item->{$column};
          $params['value'] = $value;

          // Add support property constraint
          $is_constraint_rule = ($rule instanceof ConstraintFieldValidationRuleBase);
          $validate_mode = $rule->getConfiguration()['data']['validate_mode'] ?? "default";
          // \Drupal::logger('field_validation')->notice("validate_mode:" . var_export($validate_mode,true));		
          if ($is_constraint_rule && $validate_mode == "default") {
            $constraint_name = $rule->getConstraintName();
            $constraint_options = $rule->getReplacedConstraintOptions($params);
            if ($rule->isPropertyConstraint()) {
              $real_constraint = $constraint_manager->createInstance($constraint_name, $constraint_options);
              $constraint_validator_factory =  new ConstraintValidatorFactory($class_resolver);
              $validator = $constraint_validator_factory->getInstance($real_constraint);
              $validator->initialize($this->context);
              $validator->validate($value, $real_constraint);				
            }
          }else{
            $params['delta'] = $delta;
            $config = [];
            $params['rule'] = $rule;
            $params['ruleset'] = $ruleset;
            $plugin_validator = $field_validation_rule_manager->createInstance($rule->getPluginId(), $config);
            $plugin_validator->validate($params);
		  }
        }
      }

    }
    else {
     
      // You can hard code configuration or you load from settings.
      foreach ($rules_property as $rule) {
        $value = NULL;
        // Add support property constraint
        $is_constraint_rule = ($rule instanceof ConstraintFieldValidationRuleBase);
        $validate_mode = $rule->getConfiguration()['data']['validate_mode'] ?? "default";
        // \Drupal::logger('field_validation')->notice("is_constraint_rule:" . var_export($is_constraint_rule,true));
        // \Drupal::logger('field_validation')->notice("validate_mode:" . var_export($validate_mode,true));		
        if ($is_constraint_rule && $validate_mode == "default") {
          $constraint_name = $rule->getConstraintName();
          $constraint_options = $rule->getConstraintOptions();
          if ($rule->isPropertyConstraint()) {
            $real_constraint = $constraint_manager->createInstance($constraint_name, $constraint_options);
            $constraint_validator_factory =  new ConstraintValidatorFactory($class_resolver);
            $validator = $constraint_validator_factory->getInstance($real_constraint);
            $validator->initialize($this->context);
            $validator->validate($value, $real_constraint);				
          }
        }else{  			  
          $params['value'] = NULL;
          $params['delta'] = NULL;
          $config = [];
          $params['rule'] = $rule;
          $params['ruleset'] = $ruleset;
          $plugin_validator = $field_validation_rule_manager->createInstance($rule->getPluginId(), $config);
          $plugin_validator->validate($params);
        }
      }
    }
  }

}
