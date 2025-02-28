<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the node.
 *
 * @Constraint(
 *   id = "FieldValidationConstraint",
 *   label = @Translation("Field Validation Constraint"),
 * )
 */
class FieldValidationConstraint extends Constraint {
  public $ruleset_name;
  public $rule_uuid;

  /**
   *
   */
  public function __construct($options = NULL) {
    if (is_array($options)) {
      if (key_exists('ruleset_name', $options)) {
        $this->ruleset_name = $options['ruleset_name'];
      }

      if (key_exists('rule_uuid', $options)) {
        $this->rule_uuid = $options['rule_uuid'];
      }
    }

    if (NULL !== $options && !is_array($options)) {
      $options = [
        'ruleset_name' => $options,
        'rule_uuid' => $options,
      ];
    }

    parent::__construct($options);
  }

}
