<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

/**
 * Tests PlainTextFieldValidationRule.
 *
 * @group field_validation
 *
 * @package Drupal\Tests\field_validation\Kernel
 */
class PlainTextFieldValidationRuleTest extends FieldValidationRuleBase {

  /**
   * Entity interface.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Field name.
   */
  const FIELD_NAME = 'field_plain_text';

  /**
   * Rule id.
   */
  const RULE_ID = 'plain_text_field_validation_rule';

  /**
   * Rule title.
   */
  const RULE_TITLE = 'validation rule plain text';

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
      'name' => 'plain_text_test',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $this->ruleSet->addFieldValidationRule([
      'id' => self::RULE_ID,
      'title' => self::RULE_TITLE,
      'weight' => 1,
      'field_name' => self::FIELD_NAME,
      'column' => 'value',
      'error_message' => 'Text is not plain!',
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
   * Test callback.
   */
  public function testPlainTextRule() {
    $this->assertConstraintPass(
      $this->entity,
      self::FIELD_NAME,
      'i am a plain text!'
    );
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      '<i>i am not plain text </i>',
      $this->ruleSet
    );
  }

}
