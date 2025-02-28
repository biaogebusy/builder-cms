<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

/**
 * Tests WordsFieldValidationRule.
 *
 * @group field_validation
 *
 * @package Drupal\Tests\field_validation\Kernel
 */
class WordsFieldValidationRuleTest extends FieldValidationRuleBase {

  /**
   * Entity interface.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Field name.
   */
  const FIELD_NAME = 'field_word_text';

  /**
   * Rule id.
   */
  const RULE_ID = 'words_field_validation_rule';

  /**
   * Rule title.
   */
  const RULE_TITLE = 'validation rule word text';

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
      'name' => 'words_test',
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
        'min' => 5,
        'max' => 10,
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
  public function testCountWords() {
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      'one',
      $this->ruleSet
    );
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      'one two three four five six seven eight nine ten eleven',
      $this->ruleSet
    );
    $this->assertConstraintPass(
      $this->entity,
      self::FIELD_NAME,
      'one two three four five six'
    );

    $this->updateSettings(
      [
        'min' => '1',
        'max' => '2',
      ],
      self::RULE_ID,
      self::RULE_TITLE,
      $this->ruleSet,
      self::FIELD_NAME
    );

    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      'one two three four five six',
      $this->ruleSet
    );
    $this->assertConstraintPass($this->entity, self::FIELD_NAME, 'one');
  }

}
