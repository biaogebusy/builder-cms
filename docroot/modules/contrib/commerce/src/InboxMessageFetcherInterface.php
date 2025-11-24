<?php

namespace Drupal\commerce;

/**
 * Interface for the InboxMessageFetcher.
 */
interface InboxMessageFetcherInterface {

  /**
   * Fetches the feed and saves the messages to local table.
   */
  public function fetch(): void;

  /**
   * Fetches the messages on installation for new stores.
   */
  public function fetchNewStoreMessages(): void;

}
