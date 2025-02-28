<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

/**
 * Tests LengthFieldValidationRule.
 *
 * @group field_validation
 *
 * @package Drupal\Tests\field_validation\Kernel
 */
class LengthFieldValidationRuleTest extends FieldValidationRuleBase {

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
   * Stores random string.
   *
   * @var string
   */
  protected $randomString;

  /**
   * Field name.
   */
  const FIELD_NAME = 'field_length_text';

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
    $this->min = 10;
    $this->max = 32;
    $this->setupTestArticle(self::FIELD_NAME);

    $this->ruleSet = $this->ruleSetStorage->create([
      'name' => 'length_rule_test',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $this->ruleSet->addFieldValidationRule([
      'id' => 'length_field_validation_rule',
      'title' => 'validation rule length',
      'weight' => 1,
      'field_name' => self::FIELD_NAME,
      'column' => 'value',
      'error_message' => 'Text is too long',
      'data' => [
        'min' => $this->min,
        'max' => $this->max,
      ],
    ]);
    $this->ruleSet->save();

    $this->entity = $this->nodeStorage->create([
      'type' => 'article',
      'title' => 'test',
      self::FIELD_NAME => $this->randomString,
    ]);
    $this->entity->get(self::FIELD_NAME)
      ->getFieldDefinition()
      ->addConstraint(
        'FieldValidationConstraint',
        ['ruleset_name' => $this->ruleSet->getName()]
      );
  }

  /**
   * Test LengthFieldValidationRule.
   */
  public function testLengthField() {
    $string = 'abcabcabcabc';
    $this->assertConstraintPass($this->entity, self::FIELD_NAME, $string);

    $string = '1234567891011';
    $this->assertConstraintPass($this->entity, self::FIELD_NAME, $string);

    $string = '!"Â£$%^&*()_+{}{}\'"';
    $this->assertConstraintPass($this->entity, self::FIELD_NAME, $string);

    $string = '!"Â£$%^&*()_+{}{}\'"<>?~#\|1234567890';
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      $string,
      $this->ruleSet
    );

    $string = '12456';
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      $string,
      $this->ruleSet
    );

    $string = 'ABCDEF';
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      $string,
      $this->ruleSet
    );
  }

}
