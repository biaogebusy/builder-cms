<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\field_validation\Plugin\Validation\Constraint\FieldValidationConstraint;

/**
 * Tests ItemCountFieldValidationRule.
 *
 * @group field_validation
 *
 * @package Drupal\Tests\field_validation\Kernel
 */
class ItemCountFieldValidationRuleTest extends FieldValidationRuleBase {

  /**
   * Entity interface.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Field name.
   */
  const FIELD_NAME = 'field_item_count';

  /**
   * Rule id.
   */
  const RULE_ID = 'item_count_field_validation_rule';

  /**
   * Rule title.
   */
  const RULE_TITLE = 'validation rule item count';

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

    NodeType::create([
      'type' => 'article',
      'label' => 'Article',
    ])->save();
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => self::FIELD_NAME,
      'type' => 'text',
      'cardinality' => 4,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => self::FIELD_NAME,
      'bundle' => 'article',
    ])->save();

    $this->ruleSet = $this->ruleSetStorage->create([
      'name' => 'item_count_test',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $this->ruleSet->addFieldValidationRule([
      'id' => self::RULE_ID,
      'title' => self::RULE_TITLE,
      'weight' => 1,
      'field_name' => self::FIELD_NAME,
      'column' => 'value',
      'error_message' => 'Item count error!',
      'data' => [
        'min' => 1,
        'max' => 3,
      ],
    ]);
    $this->ruleSet->save();

    $field_values = array_merge(['one'], ['two'], ['three'], ['four']);

    $this->entity = $this->nodeStorage->create([
      'type' => 'article',
      'title' => 'test count',
      self::FIELD_NAME => $field_values,
    ]);
    $this->entity->get(self::FIELD_NAME)
      ->getFieldDefinition()
      ->addConstraint(
        'FieldValidationConstraint',
        ['ruleset_name' => $this->ruleSet->getName()]
      );

  }

  /**
   * Tests ItemCountFieldValidationRule.
   */
  public function testItemCount() {

    $entity = $this->entity;
    $fieldName = self::FIELD_NAME;
    $ruleSet = $this->ruleSet;

    $violations = $entity->validate();

    $this->assertCount(4, $violations);
    $this->assertInstanceOf(
      FieldValidationConstraint::class,
      $violations[0]->getConstraint()
    );
    $this->assertEquals(
      $ruleSet->getName(),
      $violations[0]->getConstraint()->ruleset_name
    );

    $this->updateSettings(
      [
        'min' => '1',
        'max' => '5',
      ],
      self::RULE_ID,
      self::RULE_TITLE,
      $this->ruleSet,
      self::FIELD_NAME
    );

    $this->assertConstraintPass(
      $this->entity,
      self::FIELD_NAME,
      '3',
    );

  }

}
