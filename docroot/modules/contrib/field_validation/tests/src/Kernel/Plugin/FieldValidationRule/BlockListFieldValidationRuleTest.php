<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

/**
 * Tests BlocklistFieldValidationRule.
 *
 * @group field_validation
 *
 * @package Drupal\Tests\field_validation\Kernel
 */
class BlockListFieldValidationRuleTest extends FieldValidationRuleBase {

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
  const FIELD_NAME = 'field_blocklist_text';

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setupTestArticle(self::FIELD_NAME);

    $this->ruleSet = $this->ruleSetStorage->create([
      'name' => 'Blocklist_test',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $this->ruleSet->addFieldValidationRule([
      'id' => 'blocklist_field_validation_rule',
      'title' => 'validation rule blocklist',
      'weight' => 1,
      'field_name' => self::FIELD_NAME,
      'column' => 'value',
      'error_message' => 'Blocklisted words are in field',
      'data' => [
        'setting' => implode(',', $this->blocklisted),
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
   * Tests BlocklistFieldValidationRule.
   */
  public function testBlocklistRule() {
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

    $this->assertConstraintPass(
      $this->entity,
      self::FIELD_NAME,
      implode(',', $this->whitelisted)
    );
  }

}
