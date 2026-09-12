<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Tests\field_validation_legacy\Kernel\Plugin\FieldValidationRule\FieldValidationRuleBase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Tests CallbackConstraintFieldValidationRule.
 *
 * See https://www.drupal.org/project/field_validation/issues/3616699.
 *
 * @package Drupal\Tests\field_validation\Kernel
 */
#[Group('field_validation')]
class CallbackConstraintFieldValidationRuleTest extends FieldValidationRuleBase {

  /**
   * Field name.
   */
  const FIELD_NAME = 'field_callback_constraint_text';

  /**
   * Rule id.
   */
  const RULE_ID = 'callback_constraint_rule';

  /**
   * Rule title.
   */
  const RULE_TITLE = 'validation rule callback constraint';

  /**
   * Entity interface.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

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
      'name' => 'callback_constraint_test',
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
    $this->ruleSet->addFieldValidationRule([
      'id' => self::RULE_ID,
      'title' => self::RULE_TITLE,
      'weight' => 1,
      'field_name' => self::FIELD_NAME,
      'column' => 'value',
      'error_message' => 'Callback rejected this value!',
      'data' => [
        'value' => self::class . '::forbidSecretValue',
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
   * A static callback correctly runs and can add its own violation.
   *
   * Regression test for a bug where getConstraintOptions() returned the
   * callback under a 'value' key. Drupal's ConstraintFactory spreads
   * constraint options as named constructor arguments, and Symfony's
   * Callback constraint constructor parameter is named $callback, not
   * $value - so every save fataled with "Unknown named parameter $value"
   * before this method was ever reached.
   */
  public function testCallbackRuns() {
    $this->entity->get(self::FIELD_NAME)->value = 'secret';
    $violations = $this->entity->validate();

    $this->assertCount(1, $violations);
  }

  /**
   * A value the callback does not reject passes validation.
   */
  public function testCallbackPassesValidValue() {
    $this->assertConstraintPass($this->entity, self::FIELD_NAME, 'ordinary value');
  }

  /**
   * The static callback target used by the rule under test.
   *
   * Mirrors the signature Symfony's CallbackValidator invokes a static
   * array-callable with: (mixed $value, ExecutionContextInterface $context,
   * mixed $payload). This rule is field-level (isPropertyConstraint()
   * returns FALSE), so $value here is the field's item list, not a scalar.
   */
  public static function forbidSecretValue(FieldItemListInterface $items, ExecutionContextInterface $context, mixed $payload): void {
    if ($items->value === 'secret') {
      $context->addViolation('Callback rejected this value!');
    }
  }

}
