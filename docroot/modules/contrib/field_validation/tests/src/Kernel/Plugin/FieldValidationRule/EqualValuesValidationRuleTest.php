<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests EqualValuesValidationRule.
 *
 * @group field_validation
 *
 * @package Drupal\Tests\field_validation\Kernel
 */
class EqualValuesValidationRuleTest extends FieldValidationRuleBase {

  /**
   * Entity interface.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Field name.
   */
  const FIELD_NAME = 'field_equal_values';

  const FIELD_NAME_OTHER = 'field_equal_values_other';

  /**
   * Rule id.
   */
  const RULE_ID = 'equal_values_field_validation_rule';

  /**
   * Rule title.
   */
  const RULE_TITLE = 'validation rule equal values';

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
      'name' => 'equal_values_test',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $this->ruleSet->addFieldValidationRule([
      'id' => self::RULE_ID,
      'title' => self::RULE_TITLE,
      'weight' => 1,
      'field_name' => self::FIELD_NAME,
      'column' => 'value',
      'data' => ['data' => 'Equal values rule'],
      'error_message' => 'Values are equal!',
    ]);

    $this->ruleSet->addFieldValidationRule([
      'id' => self::RULE_ID,
      'title' => 'second jjj',
      'weight' => 2,
      'field_name' => self::FIELD_NAME_OTHER,
      'column' => 'value',
      'data' => ['data' => 'Equal values rule'],
      'error_message' => 'Values are equal!',
    ]);
    $this->ruleSet->save();

    $this->entity = $this->nodeStorage->create([
      'type' => 'article',
      'title' => 'Test Title',
      self::FIELD_NAME => '1337',
    ]);

    $this->entitySameBundle = $this->nodeStorage->create([
      'type' => 'article',
      'title' => 'unique 2',
      self::FIELD_NAME_OTHER => '13378',
    ]);
    $this->entitySameBundle->save();

    $this->entity->get(self::FIELD_NAME)
      ->getFieldDefinition()
      ->addConstraint(
        'FieldValidationConstraint',
        ['ruleset_name' => $this->ruleSet->getName()]
      );
    $this->entitySameBundle->get(self::FIELD_NAME_OTHER)
      ->getFieldDefinition()
      ->addConstraint(
        'FieldValidationConstraint',
        ['ruleset_name' => $this->ruleSet->getName()]
      );
  }

  /**
   * Test callback.
   */
  public function testEqualValuesRule() {
    $this->assertConstraintPass(
      $this->entitySameBundle,
      self::FIELD_NAME,
      '13378',
    );
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      '888',
      $this->ruleSet
    );
  }

}
