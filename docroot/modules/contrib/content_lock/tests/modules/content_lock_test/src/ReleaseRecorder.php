<?php

declare(strict_types=1);

namespace Drupal\content_lock_test;

use Drupal\content_lock\Event\ContentLockReleaseEvent;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Records ContentLockReleaseEvent events.
 */
class ReleaseRecorder implements EventSubscriberInterface {

  public function __construct(#[Autowire('@keyvalue')] private readonly KeyValueFactoryInterface $keyValueFactory) {
  }

  /**
   * Records entities whose content locks have been released.
   *
   * @param \Drupal\content_lock\Event\ContentLockReleaseEvent $event
   *   The content lock release event.
   */
  public function onRelease(ContentLockReleaseEvent $event): void {
    $this->keyValueFactory->get(static::class)->set('released_entities', $event->entities);
  }

  /**
   * Gets the last entities released and resets the recorder.
   *
   * @return list<array{entity_id: string, langcode: string, form_op: string|null ,entity_type: string}>
   *   The list of entities no longer content locked.
   */
  public function getReleasedEntities(): array {
    $entities = $this->keyValueFactory->get(static::class)->get('released_entities', []);
    $this->keyValueFactory->get(static::class)->delete('released_entities');
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ContentLockReleaseEvent::class => 'onRelease',
    ];
  }

}
