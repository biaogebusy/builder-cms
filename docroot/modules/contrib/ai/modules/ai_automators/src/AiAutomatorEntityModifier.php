<?php

namespace Drupal\ai_automators;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\ai_automators\Event\AutomatorConfigEvent;
use Drupal\ai_automators\Event\ProcessFieldEvent;
use Drupal\ai_automators\Event\ShouldProcessFieldEvent;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorDirectProcessInterface;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorFieldProcessInterface;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorPostCheckIfEmptyInterface;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\ai_automators\PluginManager\AiAutomatorFieldProcessManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * A helper for entity saving logic.
 */
class AiAutomatorEntityModifier {

  /**
   * The field manager.
   */
  protected EntityFieldManagerInterface $fieldManager;

  /**
   * The process manager.
   */
  protected AiAutomatorFieldProcessManager $processes;

  /**
   * The field rule manager.
   */
  protected AiFieldRules $fieldRules;

  /**
   * The event dispatcher.
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs an entity modifier.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager
   *   The field manager.
   * @param \Drupal\ai_automators\PluginManager\AiAutomatorFieldProcessManager $processes
   *   The process manager.
   * @param \Drupal\ai_automators\AiFieldRules $aiFieldRules
   *   The field rules.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityFieldManagerInterface $fieldManager, AiAutomatorFieldProcessManager $processes, AiFieldRules $aiFieldRules, EventDispatcherInterface $eventDispatcher, EntityTypeManagerInterface $entityTypeManager) {
    $this->fieldManager = $fieldManager;
    $this->processes = $processes;
    $this->fieldRules = $aiFieldRules;
    $this->eventDispatcher = $eventDispatcher;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Field form should have form altered to allow automatic content generation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for modifications.
   * @param bool $isInsert
   *   Is it an insert.
   * @param string|null $specificField
   *   If a specific field should be processed, this is the field name.
   * @param bool $isAutomated
   *   If this is an automated process or not.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The entity or NULL if no automator fields are found.
   */
  public function saveEntity(EntityInterface $entity, $isInsert = FALSE, $specificField = NULL, $isAutomated = TRUE) {
    // Only run on Content Interfaces.
    if (!($entity instanceof ContentEntityInterface)) {
      return NULL;
    }
    // Get and check so field configs exists.
    $configs = $this->entityHasConfig($entity);
    if (!count($configs)) {
      return NULL;
    }

    // Resort on weight to create in the right order.
    usort($configs, function ($a, $b) {
      if ($a['automatorConfig']['weight'] > $b['automatorConfig']['weight']) {
        return 1;
      }
      elseif ($a['automatorConfig']['weight'] < $b['automatorConfig']['weight']) {
        return -1;
      }
      return 0;
    });

    // If a specific field is set, only process that one.
    if ($specificField) {
      $configs = array_filter($configs, function ($config) use ($specificField) {
        return $config['fieldDefinition']->getName() === $specificField;
      });
      // If no configs are found, return NULL.
      if (!count($configs)) {
        return NULL;
      }
    }

    // Get possible processes.
    $workerOptions = [];
    foreach ($this->processes->getDefinitions() as $definition) {
      $workerOptions[$definition['id']] = $definition['title'] . ' - ' . $definition['description'];
    }

    // Get process for this entity.
    $processes = $this->getProcesses($configs);

    // Preprocess.
    foreach ($processes as $process) {
      $process->preProcessing($entity);
    }

    // Walk through the fields and check if we need to save anything.
    foreach ($configs as $config) {
      // Event where you can change the field configs when something exists.
      if (!empty($config['automatorConfig'])) {
        $event = new AutomatorConfigEvent($entity, $config['automatorConfig']);
        $this->eventDispatcher->dispatch($event, AutomatorConfigEvent::EVENT_NAME);
        $config['automatorConfig'] = $event->getAutomatorConfig();
      }
      // Load the processor or load direct.
      $processor = $processes[$config['automatorConfig']['worker_type']] ?? $processes['direct'];
      // If the processor is a dynamic process and its automatic, we skip it.
      if ($isAutomated && $processor instanceof AiAutomatorDirectProcessInterface) {
        continue;
      }
      if (method_exists($processor, 'isImport') && $isInsert) {
        $this->markFieldForProcessing($entity, $config['fieldDefinition'], $config['automatorConfig'], $processor);
      }
      if (!method_exists($processor, 'isImport') && !$isInsert && $config['fieldDefinition']) {
        $this->markFieldForProcessing($entity, $config['fieldDefinition'], $config['automatorConfig'], $processor);
      }
    }

    // Postprocess.
    foreach ($processes as $process) {
      $process->postProcessing($entity);
    }
    return $entity;
  }

  /**
   * Checks if an entity has fields with automator enabled.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for modifications.
   *
   * @return array
   *   An array with the field configs affected.
   */
  public function entityHasConfig(EntityInterface $entity) {
    $storage = $this->entityTypeManager->getStorage('ai_automator');
    $fields = $storage->loadByProperties([
      'entity_type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
    ]);
    $fieldDefinitions = $this->fieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

    $fieldConfigs = [];
    $automatorConfig = [];
    /** @var \Drupal\ai_automators\Entity\AiAutomator $field */
    foreach ($fields as $field) {
      // Check if enabled and return the config.
      $fieldConfigs[$field->id()]['fieldDefinition'] = $fieldDefinitions[$field->get('field_name')];
      $automatorConfig = [
        'field_name' => $field->get('field_name'),
      ];
      foreach ($field->get('plugin_config') as $key => $setting) {
        $automatorConfig[substr($key, 10)] = $setting;
      }
      $fieldConfigs[$field->id()]['automatorConfig'] = $automatorConfig;
    }

    return $fieldConfigs;
  }

  /**
   * Gets the processes available.
   *
   * @param array $configs
   *   The configurations.
   *
   * @return array
   *   Array of processes keyed by id.
   */
  public function getProcesses(array $configs) {
    // Get possible processes.
    $processes = [];
    foreach ($configs as $config) {
      $definition = $this->processes->getDefinition($config['automatorConfig']['worker_type']);
      $processes[$definition['id']] = $this->processes->createInstance($definition['id']);
    }
    return $processes;
  }

  /**
   * Checks if a field should be saved and saves it appropriately.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check for modifications.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition interface.
   * @param array $automatorConfig
   *   The OpenAI Automator settings for the field.
   * @param \Drupal\ai_automators\PluginInterfaces\AiAutomatorFieldProcessInterface $processor
   *   The processor.
   *
   * @return bool
   *   If the saving was successful or not.
   */
  protected function markFieldForProcessing(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig, AiAutomatorFieldProcessInterface $processor) {
    // Event to modify if the field should be processed.
    $event = new ProcessFieldEvent($entity, $fieldDefinition, $automatorConfig);
    $this->eventDispatcher->dispatch($event, ProcessFieldEvent::EVENT_NAME);
    // If a force reject or force process exists, we do that.
    if (in_array(ProcessFieldEvent::FIELD_FORCE_SKIP, $event->actions)) {
      return FALSE;
    }
    elseif (in_array(ProcessFieldEvent::FIELD_FORCE_PROCESS, $event->actions)) {
      return $processor->modify($entity, $fieldDefinition, $automatorConfig);
    }

    // If the type is of AiAutomatorDirectProcessInterface, it checks first.
    if ($processor instanceof AiAutomatorDirectProcessInterface && $processor->shouldProcessDirectly($entity, $fieldDefinition, $automatorConfig)) {
      // If the processor wants to process directly, we do that.
      return $processor->modify($entity, $fieldDefinition, $automatorConfig);
    }

    // Otherwise continue as normal.
    if ((!isset($automatorConfig['mode']) || $automatorConfig['mode'] === 'base') && !$this->baseShouldSave($entity, $fieldDefinition, $automatorConfig)) {
      return FALSE;
    }
    elseif (isset($automatorConfig['mode']) && $automatorConfig['mode'] === 'token' && !$this->tokenShouldSave($entity, $fieldDefinition, $automatorConfig)) {
      return FALSE;
    }

    return $processor->modify($entity, $fieldDefinition, $automatorConfig);
  }

  /**
   * If token mode, check if it should run.
   */
  private function tokenShouldSave(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Get rule.
    $rule = $this->fieldRules->findRule($automatorConfig['rule']);
    // Check if a value exists.
    $value = $entity->get($automatorConfig['field_name'])->getValue();
    $value = $rule->checkIfEmpty($value, $automatorConfig);
    $value = $this->applyPostCheckIfEmpty($rule, $entity, $value, $automatorConfig);

    $shouldProcess = FALSE;

    // If the field is empty, always process.
    if ($this->isValueEmptyAfterCheck($value)) {
      $shouldProcess = TRUE;
    }
    // If edit mode is on, check for changes in the base field.
    elseif (!empty($automatorConfig['edit_mode'])) {
      // Token mode does not require a base field, but when one is configured
      // use it for change detection.
      if (empty($automatorConfig['base_field'])) {
        $shouldProcess = TRUE;
      }
      else {
        $originalEntity = $this->getOriginalEntity($entity);
        $original = $originalEntity ? json_encode($originalEntity->get($automatorConfig['base_field'])->getValue()) : NULL;
        $current = json_encode($entity->get($automatorConfig['base_field'])->getValue());
        $shouldProcess = $current !== $original;
      }
    }

    // Dispatch event to allow modifying the decision after isEmpty check.
    $event = new ShouldProcessFieldEvent($entity, $fieldDefinition, $automatorConfig, $shouldProcess);
    $this->eventDispatcher->dispatch($event, ShouldProcessFieldEvent::EVENT_NAME);

    return $event->shouldProcess();
  }

  /**
   * If base mode, check if it should run.
   */
  private function baseShouldSave(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Check if a value exists.
    $value = $entity->get($automatorConfig['field_name'])->getValue();

    $originalEntity = $this->getOriginalEntity($entity);
    $original = $originalEntity ? json_encode($originalEntity->get($automatorConfig['base_field'])->getValue()) : NULL;
    $change = json_encode($entity->get($automatorConfig['base_field'])->getValue()) !== $original;

    // Get the rule to check the value.
    $rule = $this->fieldRules->findRule($automatorConfig['rule']);
    $value = $rule->checkIfEmpty($value, $automatorConfig);
    $value = $this->applyPostCheckIfEmpty($rule, $entity, $value, $automatorConfig);

    $shouldProcess = FALSE;

    // If edit mode is on and there is a change, return true
    // independent of current value.
    if (!empty($automatorConfig['edit_mode']) && $change) {
      $shouldProcess = TRUE;
    }
    // If no value is set, return true.
    elseif ($this->isValueEmptyAfterCheck($value)) {
      $shouldProcess = TRUE;
    }

    // Dispatch event to allow modifying the decision after isEmpty check.
    $event = new ShouldProcessFieldEvent($entity, $fieldDefinition, $automatorConfig, $shouldProcess);
    $this->eventDispatcher->dispatch($event, ShouldProcessFieldEvent::EVENT_NAME);

    return $event->shouldProcess();
  }

  /**
   * Applies optional post-check hook from rules when available.
   *
   * @param \Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface $rule
   *   The resolved rule plugin instance.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being evaluated.
   * @param mixed $value
   *   The value returned from checkIfEmpty().
   * @param array $automatorConfig
   *   The automator configuration.
   *
   * @return mixed
   *   The post-processed value.
   */
  private function applyPostCheckIfEmpty(AiAutomatorTypeInterface $rule, ContentEntityInterface $entity, mixed $value, array $automatorConfig): mixed {
    if (!is_array($value)) {
      return $value;
    }

    if ($rule instanceof AiAutomatorPostCheckIfEmptyInterface) {
      $newValue = $rule->postCheckIfEmpty($entity, $value, $automatorConfig);

      return is_array($newValue) ? $newValue : $value;
    }

    return $value;
  }

  /**
   * Determines if a normalized checkIfEmpty() value should count as empty.
   *
   * @param mixed $value
   *   The normalized value after checkIfEmpty() and optional post-check hook.
   *
   * @return bool
   *   TRUE when the value should be considered empty, FALSE otherwise.
   */
  private function isValueEmptyAfterCheck(mixed $value): bool {
    if (is_array($value)) {
      return empty($value) || empty($value[0]);
    }

    return empty($value);
  }

  /**
   * Returns the original entity during save operations (D10/D11 compatible).
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being saved.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The original entity when available.
   */
  private function getOriginalEntity(ContentEntityInterface $entity): ?ContentEntityInterface {
    // Drupal 11.2+.
    if (method_exists($entity, 'getOriginal')) {
      $original = $entity->getOriginal();

      return $original instanceof ContentEntityInterface ? $original : NULL;
    }

    // Drupal 10.x / Drupal 11.0-11.1.
    if (isset($entity->original)) {
      $original = $entity->original;

      return $original instanceof ContentEntityInterface ? $original : NULL;
    }

    return NULL;
  }

}
