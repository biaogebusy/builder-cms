<?php

namespace Drupal\dropzonejs\Events;

/**
 * Contains all events thrown by dropzonejs.
 */
final class Events {

  /**
   * The MEDIA_ENTITY_CREATE event.
   *
   * The MEDIA_ENTITY_CREATE event occurs when creating a new Media Entity,
   * before it is saved to the database.
   *
   * @var string
   */
  const MEDIA_ENTITY_CREATE = 'dropzonejs.media_entity_create';

  /**
   * The MEDIA_ENTITY_PRECREATE event.
   *
   * This event occurs when creating a new Media Entity,
   * before it is displayed in the Inline Entity Form Widget (currently only
   * used there)
   *
   * @var string
   */
  const MEDIA_ENTITY_PRECREATE = 'dropzonejs.media_entity_precreate';

}
