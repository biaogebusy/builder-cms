<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

/**
 * Tests IntegerFieldValidationRule.
 *
 * @group field_validation
 *
 * @package Drupal\Tests\field_validation\Kernel
 */
class IntegerFieldValidationTest extends FieldValidationRuleBase {

  /**
   * Stores mock ruleset.
   *
   * @var \Drupal\field_validation\Entity\FieldValidationRuleSet
   */
  protected $ruleSet;

  /**
   * Entity interface.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Field name.
   */
  const FIELD_NAME = 'field_integer_text';

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
      'name' => 'integer_test_text',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $this->ruleSet->addFieldValidationRule([
      'id' => 'integer_field_validation_rule',
      'title' => 'validation rule integer text',
      'weight' => 1,
      'field_name' => self::FIELD_NAME,
      'column' => 'value',
      'error_message' => 'Integer is not valid text field',
      'data' => [
        'min' => $this->min,
        'max' => $this->max,
      ],
    ]);
    $this->ruleSet->save();

    $this->entity = $this->nodeStorage->create([
      'type' => 'article',
      'title' => 'test',
    ]);
    $this->entity->get(self::FIELD_NAME)
      ->getFieldDefinition()
      ->addConstraint(
        'FieldValidationConstraint',
        ['ruleset_name' => $this->ruleSet->getName()]
      );
  }

  /**
   * Test IntegerFieldValidationRule.
   */
  public function testInteger() {
    // Test above maximum.
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      abs(($this->max + 1) * 10),
      $this->ruleSet
    );
    // Test below minimum.
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      ($this->min - 1) * 10,
      $this->ruleSet
    );
    // Test not integer.
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      'TestS',
      $this->ruleSet
    );

    // Test between minimum and maximum.
    $this->assertConstraintPass($this->entity, self::FIELD_NAME, 150);
    $this->assertConstraintPass($this->entity, self::FIELD_NAME, -150);

    // Test floats.
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      1.2345,
      $this->ruleSet
    );
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      1.23e3,
      $this->ruleSet
    );
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      7E-18,
      $this->ruleSet
    );

    // Test at minimum.
    $this->assertConstraintPass($this->entity, self::FIELD_NAME, $this->min);

    // Test at maximum.
    $this->assertConstraintPass($this->entity, self::FIELD_NAME, $this->max);
  }

}
