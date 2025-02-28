<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

/**
 * Tests RegexFieldValidationRule.
 *
 * @group field_validation
 *
 * @package Drupal\Tests\field_validation\Kernel
 */
class RegexFieldValidationRuleTest extends FieldValidationRuleBase {

  /**
   * Entity interface.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Field name.
   */
  const FIELD_NAME = 'field_regex_text';

  /**
   * Rule id.
   */
  const RULE_ID = 'regex_field_validation_rule';

  /**
   * Rule title.
   */
  const RULE_TITLE = 'validation rule regex text';

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
      'name' => 'regex_test',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $this->ruleSet->addFieldValidationRule([
      'id' => self::RULE_ID,
      'title' => self::RULE_TITLE,
      'weight' => 1,
      'field_name' => self::FIELD_NAME,
      'column' => 'value',
      'error_message' => 'Words error!',
      'data' => [
        'setting' => 'regex',
      ],
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
   * Tests WordFieldValidationRule.
   */
  public function testRegexPattern() {
    $this->updateSettings(
      ['setting' => '/[A-Za-z]/'],
      self::RULE_ID,
      self::RULE_TITLE,
      $this->ruleSet,
      self::FIELD_NAME
    );
    $this->assertConstraintPass($this->entity, self::FIELD_NAME, 'abcdefg');
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      '1234',
      $this->ruleSet
    );

    $this->updateSettings(
      ['setting' => '/[1-9]/'],
      self::RULE_ID,
      self::RULE_TITLE,
      $this->ruleSet,
      self::FIELD_NAME
    );
    $this->assertConstraintPass($this->entity, self::FIELD_NAME, '1234');
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      'abcdefg',
      $this->ruleSet
    );
  }

}
