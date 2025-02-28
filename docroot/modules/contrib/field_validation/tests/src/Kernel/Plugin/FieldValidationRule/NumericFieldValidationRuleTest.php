<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

/**
 * Tests NumericFieldValidationRule.
 *
 * @group field_validation
 *
 * @package Drupal\Tests\field_validation\Kernel
 */
class NumericFieldValidationRuleTest extends FieldValidationRuleBase {

  /**
   * Stores mock ruleset.
   *
   * @var \Drupal\field_validation\Entity\FieldValidationRuleSet
   */
  protected $ruleSet;

  /**
   * Field name.
   */
  const FIELD_NAME = 'field_numeric_text';

  /**
   * Entity interface.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Min range.
   *
   * @var int
   */
  protected $min;

  /**
   * Max range.
   *
   * @var int
   */
  protected $max;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->min = -255;
    $this->max = 255;
    $this->setupTestArticle(self::FIELD_NAME);

    $this->ruleSet = $this->ruleSetStorage->create([
      'name' => 'numeric',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $this->ruleSet->addFieldValidationRule([
      'id' => 'numeric_field_validation_rule',
      'title' => 'validation rule must be empty',
      'weight' => 1,
      'field_name' => self::FIELD_NAME,
      'column' => 'value',
      'error_message' => 'Field is not empty',
      'data' => [
        'min' => $this->min,
        'max' => $this->max,
      ],
    ]);
    $this->ruleSet->save();

    $this->entity = $this->nodeStorage->create([
      'type' => 'article',
      'title' => 'test',
      self::FIELD_NAME => $this->min,
    ]);
    $this->entity->get(self::FIELD_NAME)
      ->getFieldDefinition()
      ->addConstraint(
        'FieldValidationConstraint',
        ['ruleset_name' => $this->ruleSet->getName()]
      );
  }

  /**
   * Test NumericFieldValidationRule.
   */
  public function testNumeric() {
    $this->assertConstraintPass($this->entity, self::FIELD_NAME, $this->min);
    $this->assertConstraintPass($this->entity, self::FIELD_NAME, $this->max);
    $this->assertConstraintPass($this->entity, self::FIELD_NAME, $this->max - 1);
    $this->assertConstraintPass($this->entity, self::FIELD_NAME, $this->min + 1);
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      $this->max + 1,
      $this->ruleSet
    );
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      $this->max + 1,
      $this->ruleSet
    );
  }

}
