<?php

namespace Drupal\charts\Event;

/**
 * Defined events for the charts module.
 *
 * Note that submodules might have their own events.
 */
final class ChartsEvents {

  /**
   * Name of the event fired when chart types definitions are being collected.
   *
   * @Event
   *
   * @see \Drupal\charts\Event\TypesInfoEvent
   */
  const TYPE_INFO = 'charts.type_info';

}
