<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

/**
 * Tests SpecificValueFieldValidationRule.
 *
 * @group field_validation
 *
 * @package Drupal\Tests\field_validation\Kernel
 */
class SpecificValueFieldValidationRuleTest extends FieldValidationRuleBase {

  /**
   * Stores blocklisted words.
   *
   * @var array
   */
  private $blocklisted = [
    'bug',
    'issue',
    'patch',
  ];

  /**
   * Stores whitelisted words.
   *
   * @var array
   */
  private $whitelisted = [
    'release',
    'drupal',
    'docs',
  ];

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
  const FIELD_NAME = 'field_specific_value_text';

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setupTestArticle(self::FIELD_NAME);

    $this->ruleSet = $this->ruleSetStorage->create([
      'name' => 'specific_value_test',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $this->ruleSet->addFieldValidationRule([
      'id' => 'specific_value_field_validation_rule',
      'title' => 'validation rule specific value',
      'weight' => 1,
      'field_name' => self::FIELD_NAME,
      'column' => 'value',
      'error_message' => 'Words should use specific value',
      'data' => [
        'setting' => implode(',', $this->whitelisted),
      ],
    ]);
    $this->ruleSet->save();

    $this->entity = $this->nodeStorage->create([
      'type' => 'article',
      'title' => 'test',
      self::FIELD_NAME => $this->blocklisted[array_rand($this->blocklisted)],
    ]);
    $this->entity->get(self::FIELD_NAME)
      ->getFieldDefinition()
      ->addConstraint(
        'FieldValidationConstraint',
        ['ruleset_name' => $this->ruleSet->getName()]
      );
  }

  /**
   * Tests SpecificValueFieldValidationRule.
   */
  public function testSpecificValueRule() {
    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      $this->blocklisted[array_rand($this->blocklisted)],
      $this->ruleSet
    );

    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      implode(',', $this->blocklisted),
      $this->ruleSet
    );

    $this->assertConstraintPass(
      $this->entity,
      self::FIELD_NAME,
      $this->whitelisted[array_rand($this->whitelisted)]
    );

    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      implode(',', $this->whitelisted),
      $this->ruleSet
    );
  }

}
