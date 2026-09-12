<?php

declare(strict_types=1);

namespace Drupal\Tests\field_validation\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field_validation\Entity\FieldValidationRuleSet;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the not_blank_constraint_rule against a multi-value field.
 *
 * @group field_validation
 */
class NotBlankConstraintMultiValueTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'system',
    'field',
    'text',
    'entity_test',
    'field_validation',
  ];

  /**
   * The name of the multi-value field under test.
   */
  protected const FIELD_NAME = 'field_multi_text';

  /**
   * The name of the unlimited-cardinality field under test.
   */
  protected const UNLIMITED_FIELD_NAME = 'field_unlimited_text';

  /**
   * The message NotBlank reports.
   */
  protected const NOT_BLANK_MESSAGE = 'This value should not be blank.';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installConfig(['field', 'system', 'user']);

    // Create a text field with a bounded cardinality of 2 on entity_test.
    FieldStorageConfig::create([
      'field_name' => self::FIELD_NAME,
      'entity_type' => 'entity_test',
      'type' => 'string',
      'cardinality' => 2,
    ])->save();

    FieldConfig::create([
      'field_name' => self::FIELD_NAME,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => 'Multi-value text',
    ])->save();

    // And an unlimited-cardinality text field, which must not be affected.
    FieldStorageConfig::create([
      'field_name' => self::UNLIMITED_FIELD_NAME,
      'entity_type' => 'entity_test',
      'type' => 'string',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();

    FieldConfig::create([
      'field_name' => self::UNLIMITED_FIELD_NAME,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'label' => 'Unlimited text',
    ])->save();

    // Attach a not_blank_constraint_rule to both fields via a
    // field_validation_rule_set, mirroring what the UI would create.
    $rule_set = FieldValidationRuleSet::create([
      'name' => 'entity_test_entity_test',
      'label' => 'Entity test',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ]);
    $rule_manager = $this->container->get('plugin.manager.field_validation.field_validation_rule');
    foreach ([self::FIELD_NAME, self::UNLIMITED_FIELD_NAME] as $field_name) {
      $rule = $rule_manager->createInstance('not_blank_constraint_rule', []);
      $rule->setFieldName($field_name);
      $rule->setColumn('value');
      $rule_set->addFieldValidationRule($rule->getConfiguration());
    }
    $rule_set->save();

    // The field definitions for entity_test may already be cached from the
    // installEntitySchema() call above; force a rebuild so the rule set's
    // constraint gets attached to the field.
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();

    // Create user 1 so the entity_test "user_id" reference field validates
    // cleanly and does not contaminate the violations under test.
    $this->container->get('entity_type.manager')->getStorage('user')->create([
      'uid' => 1,
      'name' => 'entity-test',
      'mail' => 'entity@localhost',
      'status' => TRUE,
    ])->save();
  }

  /**
   * Filters the violations list down to ones about a given field.
   *
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
   *   The full violation list from $entity->validate().
   * @param string $field_name
   *   The field whose violations to keep.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationInterface[]
   *   Violations whose property path starts with the field name.
   */
  protected function filterFieldViolations($violations, string $field_name = self::FIELD_NAME): array {
    $matches = [];
    foreach ($violations as $violation) {
      if (str_starts_with((string) $violation->getPropertyPath(), $field_name)) {
        $matches[] = $violation;
      }
    }
    return $matches;
  }

  /**
   * Creates a test entity with the given values for the bounded field.
   *
   * @param array $values
   *   Field values for the bounded field (list of strings).
   * @param array $unlimited_values
   *   Field values for the unlimited field (list of strings).
   *
   * @return \Drupal\entity_test\Entity\EntityTest
   *   The unsaved entity.
   */
  protected function createEntity(array $values, array $unlimited_values = ['Unlimited value']): EntityTest {
    return EntityTest::create([
      'name' => $this->randomMachineName(),
      self::FIELD_NAME => $values,
      self::UNLIMITED_FIELD_NAME => $unlimited_values,
    ]);
  }

  /**
   * Both deltas filled in: no violation should be raised.
   */
  public function testBothValuesFilled(): void {
    $violations = $this->createEntity(['First value', 'Second value'])->validate();

    $this->assertCount(0, $this->filterFieldViolations($violations), (string) $violations);
  }

  /**
   * Only the first of two allowed deltas is filled in.
   *
   * This reproduces the reported bug: the widget/form layer strips the
   * empty second delta via FieldItemList::filterEmptyItems() before
   * validation ever runs, so by the time the field is validated, $items
   * only contains delta 0. A NotBlank rule on a bounded, multi-value field
   * should still flag the missing second value.
   */
  public function testOnlyFirstValueFilled(): void {
    $violations = $this->filterFieldViolations($this->createEntity(['First value'])->validate());

    $this->assertCount(1, $violations, 'Expected exactly one NotBlank violation for the missing second delta.');
    $this->assertSame(self::NOT_BLANK_MESSAGE, (string) $violations[0]->getMessage());
    // Missing deltas are reported at the field level, like a fully empty
    // field, because the widget has no row left to attach the error to.
    $this->assertSame(self::FIELD_NAME, $violations[0]->getPropertyPath());
  }

  /**
   * An explicitly blank second delta is still caught by the per-item loop.
   */
  public function testSecondValueBlank(): void {
    $violations = $this->filterFieldViolations($this->createEntity(['First value', ''])->validate());

    $this->assertCount(1, $violations);
    $this->assertSame(self::NOT_BLANK_MESSAGE, (string) $violations[0]->getMessage());
  }

  /**
   * Both deltas left blank: existing behavior, single violation on delta 0.
   */
  public function testBothValuesBlank(): void {
    $violations = $this->filterFieldViolations($this->createEntity([])->validate());

    $this->assertCount(1, $violations);
    $this->assertSame(self::NOT_BLANK_MESSAGE, (string) $violations[0]->getMessage());
    $this->assertSame(self::FIELD_NAME, $violations[0]->getPropertyPath());
  }

  /**
   * Unlimited-cardinality fields are not padded with missing deltas.
   */
  public function testUnlimitedCardinalityUnaffected(): void {
    $violations = $this->createEntity(['First value', 'Second value'], ['Only value'])->validate();
    $this->assertCount(0, $this->filterFieldViolations($violations, self::UNLIMITED_FIELD_NAME), (string) $violations);

    // A fully empty unlimited field still raises the existing violation.
    $violations = $this->filterFieldViolations($this->createEntity(['First value', 'Second value'], [])->validate(), self::UNLIMITED_FIELD_NAME);
    $this->assertCount(1, $violations);
    $this->assertSame(self::NOT_BLANK_MESSAGE, (string) $violations[0]->getMessage());
  }

}
