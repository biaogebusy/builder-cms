<?php

namespace Drupal\acquia_connector\Event;

use Drupal\Component\EventDispatcher\Event as DrupalEvent;
use Symfony\Component\EventDispatcher\Event as SymfonyEvent;

if (class_exists(DrupalEvent::class)) {
  class_alias(DrupalEvent::class, 'Drupal\acquia_connector\Event\EventBase');
}
else {
  // @phpstan-ignore-next-line
  class_alias(SymfonyEvent::class, 'Drupal\acquia_connector\Event\EventBase');
}

// @phpstan-ignore-next-line
if (FALSE) {
  /**
   * Dummy class for static code analysis for above aliases.
   */
  class EventBase {
  }
}
