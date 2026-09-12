<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Drupal\field_validation\ConstraintFieldValidationRuleBase;
use Drupal\Core\Validation\ConstraintValidatorFactory;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

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

    // Divide them into 2 array, one for field, the other for property.
    $rules_field = [];
    $rules_property = [];
    foreach ($rules_available as $rule) {
      $is_constraint_rule = ($rule instanceof ConstraintFieldValidationRuleBase);
      $validate_mode = $rule->getConfiguration()['data']['validate_mode'] ?? "default";
      // Remove "direct" mode rule.
      if ($validate_mode == "direct") {
        continue;
      }
      if ($is_constraint_rule && $validate_mode == "default" && (!$rule->isPropertyConstraint())) {
        $rules_field[] = $rule;
      }
      else {
        $rules_property[] = $rule;
      }
    }

    $field_validation_rule_manager = \Drupal::service('plugin.manager.field_validation.field_validation_rule');
    $constraint_manager = \Drupal::service('validation.constraint');
    $class_resolver = \Drupal::service('class_resolver');
    $constraint_validator_factory = new ConstraintValidatorFactory($class_resolver);

    $params = [];
    $params['items'] = $items;
    $params['context'] = $this->context;

    // Field level validation.
    foreach ($rules_field as $rule) {
      $constraint_name = $rule->getConstraintName();
      $constraint_options = $rule->getReplacedConstraintOptions($params);

      $real_constraint = $constraint_manager->createInstance($constraint_name, $constraint_options);
      $validator = $constraint_validator_factory->getInstance($real_constraint);
      $validator->initialize($this->context);
      $this->safeValidate($validator, $items, $real_constraint);
    }

    // Property level validation.
    if ($items->count() !== 0) {
      foreach ($items as $delta => $item) {
        // You can hard code configuration or you load from settings.
        foreach ($rules_property as $rule) {
          $column = $rule->getColumn();
          $value = $item->{$column};
          $params['value'] = $value;

          // Add support property constraint.
          $is_constraint_rule = ($rule instanceof ConstraintFieldValidationRuleBase);
          $validate_mode = $rule->getConfiguration()['data']['validate_mode'] ?? "default";
          // \Drupal::logger('field_validation')->notice("validate_mode:" . var_export($validate_mode,true));
          if ($is_constraint_rule && $validate_mode == "default") {
            $constraint_name = $rule->getConstraintName();
            $constraint_options = $rule->getReplacedConstraintOptions($params);
            if ($rule->isPropertyConstraint()) {
              $this->validatePropertyValue($constraint_name, $constraint_options, $value);
            }
          }
          else {
            $params['delta'] = $delta;
            $config = [];
            $params['rule'] = $rule;
            $params['ruleset'] = $ruleset;
            $plugin_validator = $field_validation_rule_manager->createInstance($rule->getPluginId(), $config);
            $plugin_validator->validate($params);
          }
        }
      }

      // The widget/form layer may have already stripped one or more blank
      // deltas via FieldItemList::filterEmptyItems() before validation ever
      // ran, so $items can under-report how many values the field is meant
      // to hold. When the field storage has a bounded (non-unlimited)
      // cardinality greater than the number of items actually present,
      // treat each missing delta the same way a fully empty field is
      // treated below, so property constraints such as NotBlank still
      // catch a blank value left in the middle of a multi-value field.
      // Unlimited-cardinality fields are left alone: there is no way to
      // know how many widget rows were rendered, so flagging anything here
      // would produce false positives for legitimately shorter lists.
      $cardinality = $items->getFieldDefinition()->getFieldStorageDefinition()->getCardinality();
      if ($cardinality !== FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED && $cardinality > $items->count()) {
        for ($missing_delta = $items->count(); $missing_delta < $cardinality; $missing_delta++) {
          foreach ($rules_property as $rule) {
            $value = NULL;
            $is_constraint_rule = ($rule instanceof ConstraintFieldValidationRuleBase);
            $validate_mode = $rule->getConfiguration()['data']['validate_mode'] ?? "default";
            if ($is_constraint_rule && $validate_mode == "default" && $rule->isPropertyConstraint()) {
              $this->validatePropertyValue($rule->getConstraintName(), $rule->getConstraintOptions(), $value);
            }
          }
        }
      }

    }
    else {

      // You can hard code configuration or you load from settings.
      foreach ($rules_property as $rule) {
        $value = NULL;
        // Add support property constraint.
        $is_constraint_rule = ($rule instanceof ConstraintFieldValidationRuleBase);
        $validate_mode = $rule->getConfiguration()['data']['validate_mode'] ?? "default";
        // \Drupal::logger('field_validation')->notice("is_constraint_rule:" . var_export($is_constraint_rule,true));
        // \Drupal::logger('field_validation')->notice("validate_mode:" . var_export($validate_mode,true));
        if ($is_constraint_rule && $validate_mode == "default") {
          $constraint_name = $rule->getConstraintName();
          $constraint_options = $rule->getConstraintOptions();
          if ($rule->isPropertyConstraint()) {
            $this->validatePropertyValue($constraint_name, $constraint_options, $value);
          }
        }
        else {
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

  /**
   * Validates a single property value against a constraint plugin.
   *
   * @param string $constraint_name
   *   The constraint plugin ID.
   * @param array $constraint_options
   *   The options to instantiate the constraint with.
   * @param mixed $value
   *   The property value to validate; NULL for a missing value.
   */
  protected function validatePropertyValue(string $constraint_name, array $constraint_options, $value): void {
    $constraint = \Drupal::service('validation.constraint')->createInstance($constraint_name, $constraint_options);
    $validator = (new ConstraintValidatorFactory(\Drupal::service('class_resolver')))->getInstance($constraint);
    $validator->initialize($this->context);
    $this->safeValidate($validator, $value, $constraint);
  }

  /**
   * Runs a constraint validator, guarding against a raw PHP warning.
   *
   * Some Symfony constraint validators (e.g. Regex) call functions like
   * preg_match() without suppressing errors, so a malformed rule
   * configuration (e.g. a regex pattern missing its delimiters) would
   * otherwise surface a raw PHP warning to the end user at rule-execution
   * time instead of a clean validation failure.
   *
   * @param \Symfony\Component\Validator\ConstraintValidatorInterface $validator
   *   The constraint validator to run.
   * @param mixed $value
   *   The value (or item list) being validated.
   * @param \Symfony\Component\Validator\Constraint $constraint
   *   The constraint being checked.
   *
   * @see https://www.drupal.org/project/field_validation/issues/3390907
   */
  protected function safeValidate($validator, $value, Constraint $constraint) {
    $warning = NULL;
    set_error_handler(function (int $errno, string $errstr) use (&$warning): bool {
      $warning = $errstr;
      // Prevent PHP's default handler from also reporting the warning.
      return TRUE;
    }, E_WARNING);
    $violations_before = count($this->context->getViolations());
    try {
      $validator->validate($value, $constraint);
    }
    finally {
      restore_error_handler();
    }

    if ($warning !== NULL) {
      \Drupal::logger('field_validation')->error('A field validation rule produced a PHP warning while validating a %constraint constraint: %warning', [
        '%constraint' => get_class($constraint),
        '%warning' => $warning,
      ]);
      // Many validators (e.g. Symfony's Regex) still record their own
      // violation after the warning, since the underlying function simply
      // returned a falsy result. Only add a fallback violation if the
      // validator did not already record one, to avoid duplicate messages.
      if (count($this->context->getViolations()) === $violations_before) {
        $this->context->buildViolation('This value is not valid.')->addViolation();
      }
    }
  }

}
