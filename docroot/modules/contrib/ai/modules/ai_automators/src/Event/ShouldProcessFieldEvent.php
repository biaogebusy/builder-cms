<?php

namespace Drupal\ai_automators\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Event to modify the decision to process a field after isEmpty check.
 *
 * This event is dispatched after the isEmpty check has been performed,
 * allowing subscribers to override the decision to run the automator rule
 * even if the field is not empty. This allows programmatic control to rerun
 * automator rules on entities that already have values, without needing to
 * empty the fields first.
 *
 * The event overrides the decision entirely (similar to ProcessFieldEvent),
 * not the field value itself. Use setShouldProcess() to force processing
 * or skip processing regardless of the isEmpty check result.
 */
class ShouldProcessFieldEvent extends Event {

  /**
   * The event name.
   */
  public const EVENT_NAME = 'ai_automator.should_process_field';

  /**
   * The entity to process.
   */
  protected ContentEntityInterface $entity;

  /**
   * The field definition.
   */
  protected FieldDefinitionInterface $fieldDefinition;

  /**
   * The configuration for the automator.
   *
   * @var array<string,mixed>
   */
  protected array $automatorConfig;

  /**
   * Whether the field should be processed based on isEmpty check.
   */
  protected bool $shouldProcess;

  /**
   * Constructs the object.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to process.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The field definition.
   * @param array $automatorConfig
   *   The configuration for the automator.
   * @param bool $shouldProcess
   *   The initial decision based on isEmpty check.
   */
  public function __construct(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig, bool $shouldProcess) {
    $this->entity = $entity;
    $this->fieldDefinition = $fieldDefinition;
    $this->automatorConfig = $automatorConfig;
    $this->shouldProcess = $shouldProcess;
  }

  /**
   * Gets the entity being processed.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * Gets the field definition being processed.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface
   *   The field definition.
   */
  public function getFieldDefinition(): FieldDefinitionInterface {
    return $this->fieldDefinition;
  }

  /**
   * Gets the automator configuration.
   *
   * @return array<string,mixed>
   *   The automator configuration.
   */
  public function getAutomatorConfig(): array {
    return $this->automatorConfig;
  }

  /**
   * Gets whether the field should be processed.
   *
   * @return bool
   *   TRUE if the field should be processed, FALSE otherwise.
   */
  public function shouldProcess(): bool {
    return $this->shouldProcess;
  }

  /**
   * Sets whether the field should be processed.
   *
   * @param bool $shouldProcess
   *   TRUE to process the field, FALSE to skip it.
   */
  public function setShouldProcess(bool $shouldProcess): void {
    $this->shouldProcess = $shouldProcess;
  }

}
