<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\NodeType;

/**
 * Tests UniqueFieldValidationRule.
 *
 * @group field_validation
 *
 * @package Drupal\Tests\field_validation\Kernel
 */
class UniqueFieldValidationRuleTest extends FieldValidationRuleBase {

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
   * Entity interface.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entityOtherBundle;
  /**
   * Entity interface.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entitySameBundle;
  /**
   * Field name for other bundle test.
   */
  const FIELD_NAME_OTHER = 'field_unique_text_other';

  /**
 * Field name.
 */
  const FIELD_NAME = 'field_unique_text';

  /**
   * Rule id.
   */
  const RULE_ID = 'unique_field_validation_rule';

  /**
   * Rule title.
   */
  const RULE_TITLE = 'validation rule unique text';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setupTestArticle(self::FIELD_NAME);

    NodeType::create([
      'type' => 'page',
      'label' => 'Basic page',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => self::FIELD_NAME,
      'bundle' => 'page',
    ])->save();

    $this->ruleSet = $this->ruleSetStorage->create([
      'name' => 'unique_field_test',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $this->ruleSet->addFieldValidationRule([
      'id' => self::RULE_ID,
      'title' => self::RULE_TITLE,
      'weight' => 1,
      'field_name' => self::FIELD_NAME,
      'column' => 'value',
      'error_message' => 'Text is not unique in scope!',
      'data' => [
        'scope' => 'bundle',
      ],
    ]);
    $this->ruleSet->save();

    // Test entity which test is trying to update.
    $this->entity = $this->nodeStorage->create([
      'type' => 'article',
      'title' => 'test',
      self::FIELD_NAME => '',
    ]);

    // Comparison entity for bundle scope.
    $this->entitySameBundle = $this->nodeStorage->create([
      'type' => 'article',
      'title' => 'unique 2',
      self::FIELD_NAME => '1337',
    ]);
    $this->entitySameBundle->save();

    // Comparison entity for entity scope.
    $this->entityOtherBundle = $this->nodeStorage->create([
      'type' => 'page',
      'title' => 'unique 2',
      self::FIELD_NAME => '1337',
    ]);
    $this->entityOtherBundle->save();

    $this->entity->get(self::FIELD_NAME)
      ->getFieldDefinition()
      ->addConstraint(
        'FieldValidationConstraint',
        ['ruleset_name' => $this->ruleSet->getName()]
      );
  }

  /**
   * Tests uniqueness of the value depending on scope.
   */
  public function testSomething() {
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      '1337',
      $this->ruleSet
    );
    $this->assertConstraintPass($this->entity, self::FIELD_NAME, '9001');
    $this->assertConstraintPass(
      $this->entityOtherBundle,
      self::FIELD_NAME,
      '9001'
    );
    $this->assertConstraintPass(
      $this->entityOtherBundle,
      self::FIELD_NAME,
      '1337'
    );
    $this->updateSettings(
      ['scope' => 'entity'],
      self::RULE_ID,
      self::RULE_TITLE,
      $this->ruleSet,
      self::FIELD_NAME
    );
    $this->assertConstraintPass($this->entity, self::FIELD_NAME, '9001');
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      '1337',
      $this->ruleSet
    );
  }

}
