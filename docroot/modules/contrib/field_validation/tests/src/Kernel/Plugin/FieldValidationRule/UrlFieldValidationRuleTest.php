<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

use Drupal\Core\Url;

/**
 * Tests UrlFieldValidationRule.
 *
 * @group field_validation
 *
 * @package Drupal\Tests\field_validation\Kernel
 */
class UrlFieldValidationRuleTest extends FieldValidationRuleBase {

  /**
   * Entity interface.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Field name.
   */
  const FIELD_NAME = 'field_url';

  /**
   * Rule id.
   */
  const RULE_ID = 'url_field_validation_rule';

  /**
   * Rule title.
   */
  const RULE_TITLE = 'url field validation rule';

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
    $this->setUpCurrentUser(['uid' => 1]);

    $this->ruleSet = $this->ruleSetStorage->create([
      'name' => 'url_field_test',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $this->ruleSet->addFieldValidationRule([
      'id' => self::RULE_ID,
      'title' => self::RULE_TITLE,
      'weight' => 1,
      'field_name' => self::FIELD_NAME,
      'column' => 'value',
      'error_message' => 'Url field validation error!',
      'data' => [
        'external' => FALSE,
        'internal' => TRUE,
      ],
    ]);
    $this->ruleSet->save();

    $this->entity = $this->nodeStorage->create([
      'type' => 'article',
      'title' => 'test url',
      self::FIELD_NAME => '',
    ]);
    $this->entity->save();
    $this->entity->get(self::FIELD_NAME)
      ->getFieldDefinition()
      ->addConstraint(
        'FieldValidationConstraint',
        ['ruleset_name' => $this->ruleSet->getName()]
      );

  }

  /**
   * Tests UrlFieldValidationRule.
   */
  public function testUrlValidationRule() {

    $this->assertConstraintFail(
      $this->entity,
      self::FIELD_NAME,
      'https://goggle.com',
      $this->ruleSet
    );

    $this->assertConstraintPass(
      $this->entity,
      self::FIELD_NAME,
      '/node/' . $this->entity->id(),
    );

  }

}
