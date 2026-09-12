<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

use Drupal\Tests\field_validation_legacy\Kernel\Plugin\FieldValidationRule\FieldValidationRuleBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests FieldValidationRuleBase::checkCondition() with a missing field.
 *
 * Regression test: checkCondition() called
 * $entity->getFieldDefinition($field_name)->getType() with no null guard.
 * A condition referencing a field the entity doesn't have (deleted after
 * the rule was saved, or hand-edited/imported config) fataled with
 * "Call to a member function getType() on null" on every real form
 * submission instead of failing closed.
 *
 * See https://www.drupal.org/project/field_validation/issues/3616699.
 *
 * @package Drupal\Tests\field_validation\Kernel
 */
#[Group('field_validation')]
class CheckConditionMissingFieldTest extends FieldValidationRuleBase {

  /**
   * Field name.
   */
  const FIELD_NAME = 'field_condition_missing_text';

  /**
   * Rule id.
   */
  const RULE_ID = 'not_blank_constraint_rule';

  /**
   * Rule title.
   */
  const RULE_TITLE = 'validation rule condition missing field';

  /**
   * Entity interface.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Stores mock ruleset.
   *
   * @var \Drupal\field_validation\Entity\FieldValidationRuleSet
   */
  protected $ruleSet;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setupTestArticle(self::FIELD_NAME);

    $this->ruleSet = $this->ruleSetStorage->create([
      'name' => 'check_condition_missing_field_test',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $this->ruleSet->addFieldValidationRule([
      'id' => self::RULE_ID,
      'title' => self::RULE_TITLE,
      'weight' => 1,
      'field_name' => self::FIELD_NAME,
      'column' => 'value',
      'error_message' => 'Should not fire - condition references a missing field.',
      'condition' => [
        // This field does not exist on the 'article' bundle.
        'field' => 'field_does_not_exist_on_this_bundle',
        'operator' => 'equals',
        'value' => 'anything',
      ],
      'data' => [],
    ]);
    $this->ruleSet->save();

    $this->entity = $this->nodeStorage->create([
      'type' => 'article',
      'title' => 'test',
      self::FIELD_NAME => '',
    ]);
    $this->entity->get(self::FIELD_NAME)
      ->getFieldDefinition()
      ->addConstraint(
        'FieldValidationConstraint',
        ['ruleset_name' => $this->ruleSet->getName()]
      );
  }

  /**
   * A condition referencing a missing field fails closed, not fatally.
   */
  public function testMissingFieldConditionDoesNotFatal() {
    // The rule would otherwise fire (NotBlank on an empty value), but the
    // broken condition should cause checkCondition() to skip the rule
    // entirely rather than throwing - so an empty value must pass.
    $violations = NULL;
    $this->entity->get(self::FIELD_NAME)->value = '';
    $violations = $this->entity->validate();

    $this->assertCount(0, $violations);
  }

}
