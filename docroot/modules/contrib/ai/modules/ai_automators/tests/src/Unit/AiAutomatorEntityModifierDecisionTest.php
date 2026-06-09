<?php

namespace Drupal\Tests\ai_automators\Unit;

use Drupal\ai_automators\AiAutomatorEntityModifier;
use Drupal\ai_automators\AiFieldRules;
use Drupal\ai_automators\Event\ShouldProcessFieldEvent;
use Drupal\ai_automators\PluginBaseClasses\RuleBase;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorFieldProcessInterface;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\ai_automators\PluginManager\AiAutomatorFieldProcessManager;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Tests automator processing decision behavior.
 *
 * @group ai_automators
 */
class AiAutomatorEntityModifierDecisionTest extends UnitTestCase {

  /**
   * Tests event override can force processing without post-check hook support.
   */
  public function testShouldProcessEventCanOverrideSkipDecision() {
    $eventDispatcher = new EventDispatcher();
    $eventDispatcher->addListener(ShouldProcessFieldEvent::EVENT_NAME, function (ShouldProcessFieldEvent $event) {
      $event->setShouldProcess(TRUE);
    });

    $fieldRules = $this->createMock(AiFieldRules::class);
    $rule = $this->createMock(AiAutomatorTypeInterface::class);
    $rule->expects($this->once())
      ->method('checkIfEmpty')
      ->willReturn([['value' => 'already filled']]);
    $fieldRules->expects($this->once())
      ->method('findRule')
      ->with('rule_id')
      ->willReturn($rule);

    $modifier = $this->createModifier($fieldRules, $eventDispatcher);

    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $entity = $this->createEntityWithFieldValue([['value' => 'already filled']]);

    $processor = $this->createMock(AiAutomatorFieldProcessInterface::class);
    $processor->expects($this->once())
      ->method('modify')
      ->willReturn(TRUE);

    $result = $modifier->callMarkFieldForProcessing(
      $entity,
      $fieldDefinition,
      [
        'mode' => 'token',
        'rule' => 'rule_id',
        'field_name' => 'field_target',
      ],
      $processor
    );

    $this->assertTrue($result);
  }

  /**
   * Tests optional post-check hook can influence decision at runtime.
   */
  public function testOptionalPostCheckHookCanForceProcessing() {
    $eventDispatcher = new EventDispatcher();

    $fieldRules = $this->createMock(AiFieldRules::class);
    $rule = $this->createMock(RuleBase::class);
    $rule->expects($this->once())
      ->method('checkIfEmpty')
      ->willReturn([['value' => 'already filled']]);
    $rule->expects($this->once())
      ->method('postCheckIfEmpty')
      ->willReturn([]);
    $fieldRules->expects($this->once())
      ->method('findRule')
      ->with('rule_id')
      ->willReturn($rule);

    $modifier = $this->createModifier($fieldRules, $eventDispatcher);

    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $entity = $this->createEntityWithFieldValue([['value' => 'already filled']]);

    $processor = $this->createMock(AiAutomatorFieldProcessInterface::class);
    $processor->expects($this->once())
      ->method('modify')
      ->willReturn(TRUE);

    $result = $modifier->callMarkFieldForProcessing(
      $entity,
      $fieldDefinition,
      [
        'mode' => 'token',
        'rule' => 'rule_id',
        'field_name' => 'field_target',
      ],
      $processor
    );

    $this->assertTrue($result);
  }

  /**
   * Tests event override can force skipping even when value is empty.
   */
  public function testShouldProcessEventCanForceSkipDecision() {
    $eventDispatcher = new EventDispatcher();
    $eventDispatcher->addListener(ShouldProcessFieldEvent::EVENT_NAME, function (ShouldProcessFieldEvent $event) {
      $event->setShouldProcess(FALSE);
    });

    $fieldRules = $this->createMock(AiFieldRules::class);
    $rule = $this->createMock(AiAutomatorTypeInterface::class);
    $rule->expects($this->once())
      ->method('checkIfEmpty')
      ->willReturn([]);
    $fieldRules->expects($this->once())
      ->method('findRule')
      ->with('rule_id')
      ->willReturn($rule);

    $modifier = $this->createModifier($fieldRules, $eventDispatcher);

    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $entity = $this->createEntityWithFieldValue([]);

    $processor = $this->createMock(AiAutomatorFieldProcessInterface::class);
    $processor->expects($this->never())
      ->method('modify');

    $result = $modifier->callMarkFieldForProcessing(
      $entity,
      $fieldDefinition,
      [
        'mode' => 'token',
        'rule' => 'rule_id',
        'field_name' => 'field_target',
      ],
      $processor
    );

    $this->assertFalse($result);
  }

  /**
   * Tests non-array checkIfEmpty() return is handled without type errors.
   */
  public function testNonArrayCheckIfEmptyReturnIsHandledSafely() {
    $eventDispatcher = new EventDispatcher();

    $fieldRules = $this->createMock(AiFieldRules::class);
    $rule = $this->createMock(RuleBase::class);
    $rule->expects($this->once())
      ->method('checkIfEmpty')
      ->willReturn(FALSE);
    $rule->expects($this->never())
      ->method('postCheckIfEmpty');
    $fieldRules->expects($this->once())
      ->method('findRule')
      ->with('rule_id')
      ->willReturn($rule);

    $modifier = $this->createModifier($fieldRules, $eventDispatcher);

    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $entity = $this->createEntityWithFieldValue([['value' => 'already filled']]);

    $processor = $this->createMock(AiAutomatorFieldProcessInterface::class);
    $processor->expects($this->once())
      ->method('modify')
      ->willReturn(TRUE);

    $result = $modifier->callMarkFieldForProcessing(
      $entity,
      $fieldDefinition,
      [
        'mode' => 'token',
        'rule' => 'rule_id',
        'field_name' => 'field_target',
      ],
      $processor
    );

    $this->assertTrue($result);
  }

  /**
   * Tests object checkIfEmpty() return does not trigger array access errors.
   */
  public function testObjectCheckIfEmptyReturnIsHandledSafely() {
    $eventDispatcher = new EventDispatcher();

    $fieldRules = $this->createMock(AiFieldRules::class);
    $rule = $this->createMock(RuleBase::class);
    $rule->expects($this->once())
      ->method('checkIfEmpty')
      ->willReturn((object) ['value' => 'already filled']);
    $rule->expects($this->never())
      ->method('postCheckIfEmpty');
    $fieldRules->expects($this->once())
      ->method('findRule')
      ->with('rule_id')
      ->willReturn($rule);

    $modifier = $this->createModifier($fieldRules, $eventDispatcher);

    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $entity = $this->createEntityWithFieldValue([['value' => 'already filled']]);

    $processor = $this->createMock(AiAutomatorFieldProcessInterface::class);
    $processor->expects($this->never())
      ->method('modify');

    $result = $modifier->callMarkFieldForProcessing(
      $entity,
      $fieldDefinition,
      [
        'mode' => 'token',
        'rule' => 'rule_id',
        'field_name' => 'field_target',
      ],
      $processor
    );

    $this->assertFalse($result);
  }

  /**
   * Tests base mode runs when edit mode is enabled and source value changed.
   */
  public function testBaseModeEditModeRunsWhenSourceChanges() {
    $eventDispatcher = new EventDispatcher();

    $fieldRules = $this->createMock(AiFieldRules::class);
    $rule = $this->createMock(AiAutomatorTypeInterface::class);
    $rule->expects($this->once())
      ->method('checkIfEmpty')
      ->willReturn([['value' => 'already filled']]);
    $fieldRules->expects($this->once())
      ->method('findRule')
      ->with('rule_id')
      ->willReturn($rule);

    $modifier = $this->createModifier($fieldRules, $eventDispatcher);

    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $entity = $this->createBaseModeEntity(
      [['value' => 'already filled']],
      [['value' => 'new base text']],
      [['value' => 'old base text']]
    );

    $processor = $this->createMock(AiAutomatorFieldProcessInterface::class);
    $processor->expects($this->once())
      ->method('modify')
      ->willReturn(TRUE);

    $result = $modifier->callMarkFieldForProcessing(
      $entity,
      $fieldDefinition,
      [
        'mode' => 'base',
        'edit_mode' => TRUE,
        'rule' => 'rule_id',
        'field_name' => 'field_target',
        'base_field' => 'field_source',
      ],
      $processor
    );

    $this->assertTrue($result);
  }

  /**
   * Tests base mode skips when edit mode is enabled and source is unchanged.
   */
  public function testBaseModeEditModeSkipsWhenSourceIsUnchanged() {
    $eventDispatcher = new EventDispatcher();

    $fieldRules = $this->createMock(AiFieldRules::class);
    $rule = $this->createMock(AiAutomatorTypeInterface::class);
    $rule->expects($this->once())
      ->method('checkIfEmpty')
      ->willReturn([['value' => 'already filled']]);
    $fieldRules->expects($this->once())
      ->method('findRule')
      ->with('rule_id')
      ->willReturn($rule);

    $modifier = $this->createModifier($fieldRules, $eventDispatcher);

    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $entity = $this->createBaseModeEntity(
      [['value' => 'already filled']],
      [['value' => 'same base text']],
      [['value' => 'same base text']]
    );

    $processor = $this->createMock(AiAutomatorFieldProcessInterface::class);
    $processor->expects($this->never())
      ->method('modify');

    $result = $modifier->callMarkFieldForProcessing(
      $entity,
      $fieldDefinition,
      [
        'mode' => 'base',
        'edit_mode' => TRUE,
        'rule' => 'rule_id',
        'field_name' => 'field_target',
        'base_field' => 'field_source',
      ],
      $processor
    );

    $this->assertFalse($result);
  }

  /**
   * Tests token mode runs when edit mode is enabled and base field changed.
   */
  public function testTokenModeEditModeRunsWhenBaseFieldChanges() {
    $eventDispatcher = new EventDispatcher();

    $fieldRules = $this->createMock(AiFieldRules::class);
    $rule = $this->createMock(AiAutomatorTypeInterface::class);
    $rule->expects($this->once())
      ->method('checkIfEmpty')
      ->willReturn([['value' => 'already filled']]);
    $fieldRules->expects($this->once())
      ->method('findRule')
      ->with('rule_id')
      ->willReturn($rule);

    $modifier = $this->createModifier($fieldRules, $eventDispatcher);

    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $entity = $this->createBaseModeEntity(
      [['value' => 'already filled']],
      [['value' => 'new base text']],
      [['value' => 'old base text']]
    );

    $processor = $this->createMock(AiAutomatorFieldProcessInterface::class);
    $processor->expects($this->once())
      ->method('modify')
      ->willReturn(TRUE);

    $result = $modifier->callMarkFieldForProcessing(
      $entity,
      $fieldDefinition,
      [
        'mode' => 'token',
        'edit_mode' => TRUE,
        'rule' => 'rule_id',
        'field_name' => 'field_target',
        'base_field' => 'field_source',
      ],
      $processor
    );

    $this->assertTrue($result);
  }

  /**
   * Tests token mode skips when edit mode is enabled but base field unchanged.
   */
  public function testTokenModeEditModeSkipsWhenBaseFieldUnchanged() {
    $eventDispatcher = new EventDispatcher();

    $fieldRules = $this->createMock(AiFieldRules::class);
    $rule = $this->createMock(AiAutomatorTypeInterface::class);
    $rule->expects($this->once())
      ->method('checkIfEmpty')
      ->willReturn([['value' => 'already filled']]);
    $fieldRules->expects($this->once())
      ->method('findRule')
      ->with('rule_id')
      ->willReturn($rule);

    $modifier = $this->createModifier($fieldRules, $eventDispatcher);

    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $entity = $this->createBaseModeEntity(
      [['value' => 'already filled']],
      [['value' => 'same base text']],
      [['value' => 'same base text']]
    );

    $processor = $this->createMock(AiAutomatorFieldProcessInterface::class);
    $processor->expects($this->never())
      ->method('modify');

    $result = $modifier->callMarkFieldForProcessing(
      $entity,
      $fieldDefinition,
      [
        'mode' => 'token',
        'edit_mode' => TRUE,
        'rule' => 'rule_id',
        'field_name' => 'field_target',
        'base_field' => 'field_source',
      ],
      $processor
    );

    $this->assertFalse($result);
  }

  /**
   * Tests token mode runs when edit mode is enabled but no base field set.
   */
  public function testTokenModeEditModeRunsWhenNoBaseField() {
    $eventDispatcher = new EventDispatcher();

    $fieldRules = $this->createMock(AiFieldRules::class);
    $rule = $this->createMock(AiAutomatorTypeInterface::class);
    $rule->expects($this->once())
      ->method('checkIfEmpty')
      ->willReturn([['value' => 'already filled']]);
    $fieldRules->expects($this->once())
      ->method('findRule')
      ->with('rule_id')
      ->willReturn($rule);

    $modifier = $this->createModifier($fieldRules, $eventDispatcher);

    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $entity = $this->createEntityWithFieldValue([['value' => 'already filled']]);

    $processor = $this->createMock(AiAutomatorFieldProcessInterface::class);
    $processor->expects($this->once())
      ->method('modify')
      ->willReturn(TRUE);

    $result = $modifier->callMarkFieldForProcessing(
      $entity,
      $fieldDefinition,
      [
        'mode' => 'token',
        'edit_mode' => TRUE,
        'rule' => 'rule_id',
        'field_name' => 'field_target',
      ],
      $processor
    );

    $this->assertTrue($result);
  }

  /**
   * Tests token mode skips when edit mode is disabled and field has value.
   */
  public function testTokenModeSkipsWhenEditModeDisabled() {
    $eventDispatcher = new EventDispatcher();

    $fieldRules = $this->createMock(AiFieldRules::class);
    $rule = $this->createMock(AiAutomatorTypeInterface::class);
    $rule->expects($this->once())
      ->method('checkIfEmpty')
      ->willReturn([['value' => 'already filled']]);
    $fieldRules->expects($this->once())
      ->method('findRule')
      ->with('rule_id')
      ->willReturn($rule);

    $modifier = $this->createModifier($fieldRules, $eventDispatcher);

    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $entity = $this->createEntityWithFieldValue([['value' => 'already filled']]);

    $processor = $this->createMock(AiAutomatorFieldProcessInterface::class);
    $processor->expects($this->never())
      ->method('modify');

    $result = $modifier->callMarkFieldForProcessing(
      $entity,
      $fieldDefinition,
      [
        'mode' => 'token',
        'edit_mode' => FALSE,
        'rule' => 'rule_id',
        'field_name' => 'field_target',
      ],
      $processor
    );

    $this->assertFalse($result);
  }

  /**
   * Creates a modifier with mocked dependencies and a real event dispatcher.
   *
   * @param \Drupal\ai_automators\AiFieldRules $fieldRules
   *   Mocked field rules service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher
   *   Event dispatcher with optional listeners.
   *
   * @return \Drupal\Tests\ai_automators\Unit\TestableAiAutomatorEntityModifier
   *   Test wrapper exposing markFieldForProcessing().
   */
  private function createModifier(AiFieldRules $fieldRules, EventDispatcher $eventDispatcher): TestableAiAutomatorEntityModifier {
    $fieldManager = $this->createMock(EntityFieldManagerInterface::class);
    $processes = $this->createMock(AiAutomatorFieldProcessManager::class);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    return new TestableAiAutomatorEntityModifier(
      $fieldManager,
      $processes,
      $fieldRules,
      $eventDispatcher,
      $entityTypeManager
    );
  }

  /**
   * Creates a content entity stub for token mode value checks.
   *
   * @param array $value
   *   Field values to return for the automator target field.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Entity mock with field values.
   */
  private function createEntityWithFieldValue(array $value): ContentEntityInterface {
    $fieldItemList = $this->createMock(FieldItemListInterface::class);
    $fieldItemList->expects($this->atLeastOnce())
      ->method('getValue')
      ->willReturn($value);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->atLeastOnce())
      ->method('get')
      ->with('field_target')
      ->willReturn($fieldItemList);

    return $entity;
  }

  /**
   * Creates an entity stub for base mode value and source-change checks.
   *
   * @param array $fieldValue
   *   The current target field values.
   * @param array $baseValue
   *   The current base/source field values.
   * @param array|null $originalBaseValue
   *   The original base/source field values.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   Entity mock with field values and optional original entity.
   */
  private function createBaseModeEntity(array $fieldValue, array $baseValue, ?array $originalBaseValue = NULL): ContentEntityInterface {
    $targetFieldItemList = $this->createMock(FieldItemListInterface::class);
    $targetFieldItemList->expects($this->atLeastOnce())
      ->method('getValue')
      ->willReturn($fieldValue);

    $baseFieldItemList = $this->createMock(FieldItemListInterface::class);
    $baseFieldItemList->expects($this->atLeastOnce())
      ->method('getValue')
      ->willReturn($baseValue);

    $entity = $this->createMock(ContentEntityBase::class);
    $entity->expects($this->atLeastOnce())
      ->method('get')
      ->willReturnCallback(function (string $fieldName) use ($targetFieldItemList, $baseFieldItemList): FieldItemListInterface {
        return match ($fieldName) {
          'field_target' => $targetFieldItemList,
          'field_source' => $baseFieldItemList,
          default => throw new \InvalidArgumentException("Unexpected field: {$fieldName}"),
        };
      });
    $supportsGetOriginal = method_exists(ContentEntityBase::class, 'getOriginal');

    if (is_array($originalBaseValue)) {
      $originalBaseFieldItemList = $this->createMock(FieldItemListInterface::class);
      $originalBaseFieldItemList->expects($this->atLeastOnce())
        ->method('getValue')
        ->willReturn($originalBaseValue);

      $originalEntity = $this->createMock(ContentEntityBase::class);
      $originalEntity->expects($this->atLeastOnce())
        ->method('get')
        ->willReturnCallback(function (string $fieldName) use ($originalBaseFieldItemList): FieldItemListInterface {
          if ($fieldName === 'field_source') {
            return $originalBaseFieldItemList;
          }
          throw new \InvalidArgumentException("Unexpected original field: {$fieldName}");
        });
      if ($supportsGetOriginal) {
        /** @var \PHPUnit\Framework\MockObject\MockObject $entityMock */
        $entityMock = $entity;
        $entityMock->expects($this->atLeastOnce())
          ->method('getOriginal')
          ->willReturn($originalEntity);
      }
      else {
        $entity->expects($this->atLeastOnce())
          ->method('__isset')
          ->willReturnCallback(static fn(string $name): bool => $name === 'original');
        $entity->expects($this->atLeastOnce())
          ->method('__get')
          ->willReturnCallback(static fn(string $name) => $name === 'original' ? $originalEntity : NULL);
      }
    }
    else {
      if ($supportsGetOriginal) {
        /** @var \PHPUnit\Framework\MockObject\MockObject $entityMock */
        $entityMock = $entity;
        $entityMock->expects($this->atLeastOnce())
          ->method('getOriginal')
          ->willReturn(NULL);
      }
    }

    return $entity;
  }

}

/**
 * Test wrapper to expose protected markFieldForProcessing().
 */
class TestableAiAutomatorEntityModifier extends AiAutomatorEntityModifier {

  /**
   * Calls the protected markFieldForProcessing() method.
   */
  public function callMarkFieldForProcessing(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig, AiAutomatorFieldProcessInterface $processor): bool {
    return $this->markFieldForProcessing($entity, $fieldDefinition, $automatorConfig, $processor);
  }

}
