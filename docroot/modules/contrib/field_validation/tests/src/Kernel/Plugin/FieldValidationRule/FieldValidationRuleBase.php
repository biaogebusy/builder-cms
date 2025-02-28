<?php

namespace Drupal\Tests\field_validation\Kernel\Plugin\FieldValidationRule;

use Drupal\Core\Entity\EntityInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field_validation\Entity\FieldValidationRuleSet;
use Drupal\field_validation\Plugin\Validation\Constraint\FieldValidationConstraint;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * FieldValidationRuleBase class.
 *
 * Provides helper methods shared across tests.
 */
abstract class FieldValidationRuleBase extends EntityKernelTestBase {

  /**
   * {@inheritDoc}
   */
  protected static $modules = ['node', 'field_validation', 'path_alias'];

  /**
   * NodeStorage interface.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * FieldValidationRuleSet storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $ruleSetStorage;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('path_alias');
    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
    $this->ruleSetStorage = $this->entityTypeManager->getStorage(
      'field_validation_rule_set'
    );
  }

  /**
   * Helper method to update configuration of ruleset.
   *
   * @param array $data
   *   Data array to update.
   * @param string $ruleId
   *   New rule id.
   * @param string $ruleTitle
   *   New rule title.
   * @param \Drupal\field_validation\Entity\FieldValidationRuleSet $ruleSet
   *   Check for ruleset specific constraint.
   * @param string $fieldName
   *   Set the field.
   * @param string $error
   *   Set the error message.
   *
   * @return \Drupal\field_validation\Entity\FieldValidationRuleSet
   *   Returns new ruleset with updated settings.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function updateSettings(
    array $data,
    string $ruleId,
    string $ruleTitle,
    FieldValidationRuleSet $ruleSet,
    string $fieldName,
    string $error = 'Something is wrong!'
  ) {
    $fieldValidationRules = $ruleSet->getFieldValidationRules();
    foreach ($fieldValidationRules as $fieldValidationRule) {
      $ruleSet->deleteFieldValidationRule($fieldValidationRule);
    }
    $ruleSet->addFieldValidationRule([
      'id' => $ruleId,
      'title' => $ruleTitle,
      'weight' => 1,
      'field_name' => $fieldName,
      'column' => 'value',
      'error_message' => $error,
      'data' => $data,
    ]);
    $ruleSet->save();

    return $ruleSet;
  }

  /**
   * Helper method for empty and malformed inputs which must fail.
   *
   * This helper method is used in validations where certain pattern exists,
   * eg. IP or Phone numer.
   *
   * @param string $fieldName
   *   Which field to validate.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   EntityInterface to validate.
   * @param \Drupal\field_validation\Entity\FieldValidationRuleSet $ruleSet
   *   Check for ruleset specific constraint.
   */
  protected function emptyAndMalformed(
    string $fieldName,
    EntityInterface $entity,
    FieldValidationRuleSet $ruleSet
  ) {
    $this->assertConstraintFail($entity, $fieldName, ' ', $ruleSet);
    $this->assertConstraintFail(
      $entity,
      $fieldName,
      '192.::232:aspod:',
      $ruleSet
    );
  }

  /**
   * Helper method for passed assertions.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   EntityInterface to validate.
   * @param string $fieldName
   *   Which field to validate.
   * @param mixed $value
   *   Which value to validate.
   */
  public function assertConstraintPass(
    EntityInterface $entity,
    string $fieldName,
    $value
  ) {
    $entity->get($fieldName)->value = $value;
    $violations = $entity->validate();

    $this->assertCount(0, $violations);
  }

  /**
   * Helper method for failed assertions.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   EntityInterface to validate.
   * @param string $fieldName
   *   Field to validate.
   * @param string $value
   *   Value to validate.
   * @param \Drupal\field_validation\Entity\FieldValidationRuleSet $ruleSet
   *   Ruleset to check constraints.
   */
  protected function assertConstraintFail(
    EntityInterface $entity,
    string $fieldName,
    string $value,
    FieldValidationRuleSet $ruleSet
  ) {
    $entity->get($fieldName)->value = $value;
    $violations = $entity->validate();

    $this->assertCount(1, $violations);
    $this->assertInstanceOf(
      FieldValidationConstraint::class,
      $violations[0]->getConstraint()
    );
    $this->assertEquals(
      $ruleSet->getName(),
      $violations[0]->getConstraint()->ruleset_name
    );
  }

  /**
   * Sets up the test article on which rules are tested.
   *
   * @param string $fieldName
   *   Field name to set.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setupTestArticle(string $fieldName) {
    NodeType::create([
      'type' => 'article',
      'label' => 'Article',
    ])->save();

    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => $fieldName,
      'type' => 'text',
    ])->save();

    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => $fieldName,
      'bundle' => 'article',
    ])->save();
  }

}
