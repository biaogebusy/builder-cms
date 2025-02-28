<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
/**
 * Tests MatchFieldFieldValidationRule.
 *
 * @group field_validation
 *
 * @package Drupal\Tests\field_validation\Kernel
 */
class MatchFieldFieldValidationRuleTest extends FieldValidationRuleBase {

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
   * Rule id.
   */
  const RULE_ID = 'match_field_field_validation_rule';

  /**
   * Rule title.
   */
  const RULE_TITLE = 'Match against a field value';

  /**
   * Field name.
   */
  const FIELD_NAME = 'field_match';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setupTestArticle(self::FIELD_NAME);
	
    NodeType::create([
      'type' => 'person',
      'label' => 'Person',
    ])->save();

    $person1 = $this->nodeStorage->create([
      'type' => 'person',
      'title' => 'Name1',
    ]);
    $person1->save();

    $person2 = $this->nodeStorage->create([
      'type' => 'person',
      'title' => 'Name2',
    ]);
    $person2->save();

    $this->ruleSet = $this->ruleSetStorage->create([
      'name' => 'Matchfield_test',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);

    $this->ruleSet->addFieldValidationRule([
      'id' => self:: RULE_ID,
      'title' => self:: RULE_TITLE,
      'weight' => 1,
      'field_name' => self::FIELD_NAME,
      'error_message' => 'Not matching against field value entered',
      'column' => 'value',
       'data' => [
        'entity_type' => "node",
        'bundle' => "person",
        'field_name' => "title",
       ]
    ]);

    $this->ruleSet->save();

    $this->entity = $this->nodeStorage->create([
      'type' => 'article',
      'title' => 'test',
      self::FIELD_NAME => 'field_name_value',
    ]);

    $this->entity->get(self::FIELD_NAME)
      ->getFieldDefinition()
      ->addConstraint(
        'FieldValidationConstraint',
        ['ruleset_name' => $this->ruleSet->getName()]
      );
  }

  /**
   * 
   * Tests MatchFieldFieldValidationRule.
   */
  public function testMatchFieldRule() {
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      'meme',
      $this->ruleSet
    );

    $this->assertConstraintPass(
      $this->entity,
      self::FIELD_NAME,
      'Name1'
    );
  }
}
