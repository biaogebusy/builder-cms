<?php

namespace Drupal\flood_control;

/**
 * Interface for FloodUnblockManager.
 */
interface FloodUnblockManagerInterface {

  /**
   * Gets the user link or location string for an identifier.
   *
   * @param string $results
   *   An array containing the identifiers from the flood table.
   *
   * @return array
   *   List of identifiers, keyed by the original identifier, containing
   *   user link or location string or just the unchanged identifier.
   */
  public function fetchIdentifiers($results);

  /**
   * Removes rows from flood table.
   *
   * @param string $fid
   *   The flood table entry ID.
   */
  public function floodUnblockClearEvent($fid);

  /**
   * Gets metadata about events.
   *
   * @return array
   *   List of events, keyed by the Drupal flood event name containing
   *   type and label.
   */
  public function getEvents();

  /**
   * Gets the type of an event.
   *
   * @param string $event
   *   The event descriptor.
   *
   * @return string
   *   Event Type.
   */
  public function getEventType($event);

  /**
   * Gets the label of an event.
   *
   * @param string $event
   *   The event descriptor.
   *
   * @return string
   *   Event Label.
   */
  public function getEventLabel($event);

  /**
   * Provides identifier's flood status.
   *
   * @param string $identifier
   *   The identifier: IP address and/or UID.
   * @param string $event
   *   The flood event name.
   *
   * @return bool
   *   Whether the identifier is blocked.
   */
  public function isBlocked($identifier, $event);

  /**
   * Provides list of event IDs.
   *
   * @param string $event
   *   The flood event name.
   * @param string $identifier
   *   Database LIKE query parameter for matching event IDs.
   *
   * @return array
   *   List of event IDs.
   */
  public function getEventIds($event, $identifier = NULL);

}
