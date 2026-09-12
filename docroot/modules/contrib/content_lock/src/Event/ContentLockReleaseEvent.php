<?php

namespace Drupal\content_lock\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityInterface;

/**
 * Respond to a content locks being released.
 */
class ContentLockReleaseEvent extends Event {

  /**
   * The event name.
   *
   * @deprecated in content_lock:3.0.0 and is removed from content_lock:4.0.0.
   *   Use the class name instead.
   *
   * @see https://www.drupal.org/project/content_lock/issues/3598236
   */
  const EVENT_NAME = self::class;

  /**
   * The list of entities no longer content locked.
   *
   * @var list<array{entity_id: string, langcode: string, form_op: string|null, entity_type: string}>
   *
   * @phpstan-ignore property.uninitializedReadonly
   */
  public readonly array $entities;

  /**
   * {@inheritdoc}
   */
  protected function __construct() {
  }

  /**
   * Creates an event for a single entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $langcode
   *   The entity's langcode.
   * @param string|null $formOp
   *   The form operation.
   *
   * @return static
   */
  public static function createFromEntity(EntityInterface $entity, string $langcode, ?string $formOp): static {
    $event = new static();
    $event->entities = [
      [
        'entity_id' => $entity->id(),
        'langcode' => $langcode,
        'form_op' => $formOp,
        'entity_type' => $entity->getEntityTypeId(),
      ],
    ];
    return $event;
  }

  /**
   * Creates an event for multiple entities from the content_lock table rows.
   *
   * @param list<object{entity_id: string, langcode: string, form_op: string|null, entity_type: string}&\stdClass> $rows
   *   Rows selected from the content_lock table.
   *
   * @return static
   */
  public static function createFromQueryResult(array $rows): static {
    $event = new static();
    $entities = [];
    foreach ($rows as $row) {
      $entities[] = [
        'entity_id' => $row->entity_id,
        'langcode' => $row->langcode,
        'form_op' => $row->form_op,
        'entity_type' => $row->entity_type,
      ];
    }
    $event->entities = $entities;
    return $event;
  }

  /**
   * Implements PHP magic __get() method.
   */
  public function __get(string $name) {
    @trigger_error("Accessing content lock release event information via ->entity_id, ->entity_type, ->langcode, or ->form_op is deprecated in content_lock:3.0.0 and removed in content_lock:4.0.0. Use ->entities instead. See https://www.drupal.org/project/content_lock/issues/3530760", E_USER_DEPRECATED);
    return match ($name) {
      'entity_id' => $this->entities[0]['entity_id'],
      'entity_type' => $this->entities[0]['entity_type'],
      'langcode' => $this->entities[0]['langcode'],
      'form_op' => $this->entities[0]['form_op'],
    };
  }

}
