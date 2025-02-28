<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field_validation\Plugin\Validation\Constraint\FieldValidationConstraint;

/**
 * Tests OneOfSeveralValidationRule.
 *
 * @group field_validation
 *
 * @package Drupal\Tests\field_validation\Kernel
 */
class OneOfSeveralValidationRuleTest extends FieldValidationRuleBase {

  /**
   * Entity interface.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Field name.
   */
  const FIELD_NAME = 'field_one_of_several';

  const FIELD_NAME_OTHER = 'field_one_of_several_other';

  /**
   * Rule id.
   */
  const RULE_ID = 'one_of_several_validation_rule';

  /**
   * Rule title.
   */
  const RULE_TITLE = 'one of several validation rule';


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

    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => self::FIELD_NAME_OTHER,
      'type' => 'text',
    ])->save();

    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => self::FIELD_NAME_OTHER,
      'bundle' => 'article',
    ])->save();

    $this->ruleSet = $this->ruleSetStorage->create([
      'name' => 'one_of_several_values_test',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $this->ruleSet->addFieldValidationRule([
      'id' => self::RULE_ID,
      'title' => self::RULE_TITLE,
      'weight' => 1,
      'field_name' => self::FIELD_NAME,
      'column' => 'value',
      'data' => ['data' => 'OneOfSeveral'],
      'error_message' => 'Several values error!',
    ]);

    $this->ruleSet->addFieldValidationRule([
      'id' => self::RULE_ID,
      'title' => "one of several validation rule2",
      'weight' => 2,
      'field_name' => self::FIELD_NAME_OTHER,
      'column' => 'value',
      'data' => ['data' => 'OneOfSeveral'],
      'error_message' => 'Several values error!',
    ]);

    $this->ruleSet->save();

    $this->entity = $this->nodeStorage->create([
      'type' => 'article',
      'title' => 'Test Several',
      self::FIELD_NAME => '',
      self::FIELD_NAME_OTHER => '',
    ]);

    $this->entity->get(self::FIELD_NAME)
      ->getFieldDefinition()
      ->addConstraint(
        'FieldValidationConstraint',
        ['ruleset_name' => $this->ruleSet->getName()]
      );
    $this->entity->get(self::FIELD_NAME_OTHER)
      ->getFieldDefinition()
      ->addConstraint(
        'FieldValidationConstraint',
        ['ruleset_name' => $this->ruleSet->getName()]
      );
  }

  /**
   * Test callback.
   */
  public function testOneOfSeveralValidationRule() {
    $this->assertConstraintPass(
      $this->entity,
      self::FIELD_NAME,
       'test pass',
    );

    $this->entity->get(self::FIELD_NAME)->value = '';
    $violations = $this->entity->validate();

    $this->assertCount(2, $violations);
    $this->assertInstanceOf(
      FieldValidationConstraint::class,
      $violations[0]->getConstraint()
    );
    $this->assertEquals(
      $this->ruleSet->getName(),
      $violations[0]->getConstraint()->ruleset_name
    );
  }

}
