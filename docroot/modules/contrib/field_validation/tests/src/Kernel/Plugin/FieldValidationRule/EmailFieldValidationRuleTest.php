<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

/**
 * Tests EmailFieldValidationRule.
 *
 * @group field_validation
 *
 * @package Drupal\Tests\field_validation\Kernel
 */
class EmailFieldValidationRuleTest extends FieldValidationRuleBase {

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
  const FIELD_NAME = 'field_email_text';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setupTestArticle(self::FIELD_NAME);

    $this->ruleSet = $this->ruleSetStorage->create([
      'name' => 'email_test',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $this->ruleSet->addFieldValidationRule([
      'id' => 'email_field_validation_rule',
      'title' => 'validation rule email',
      'weight' => 1,
      'field_name' => self::FIELD_NAME,
      'column' => 'value',
      'error_message' => 'Email is not valid',
    ]);
    $this->ruleSet->save();

    $this->entity = $this->nodeStorage->create([
      'type' => 'article',
      'title' => 'test',
      'field_email_text' => 'invalid.com',
    ]);
    $this->entity->get(self::FIELD_NAME)
      ->getFieldDefinition()
      ->addConstraint(
        'FieldValidationConstraint',
        ['ruleset_name' => $this->ruleSet->getName()]
      );
  }

  /**
   * Test EmailFieldValidationRule.
   */
  public function testEmailRule() {
    // Tests invalid input.
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      'invalid.com',
      $this->ruleSet
    );

    // Tests valid input.
    $this->assertConstraintPass(
      $this->entity,
      self::FIELD_NAME,
      'valid@valid.com'
    );

    // Tests empty and malformed.
    $this->emptyAndMalformed(self::FIELD_NAME, $this->entity, $this->ruleSet);
  }

}
