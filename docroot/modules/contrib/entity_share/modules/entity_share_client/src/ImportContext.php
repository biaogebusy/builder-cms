<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client;

/**
 * Class ImportContext.
 *
 * Contains properties to store data during import.
 *
 * @package Drupal\entity_share_client
 */
class ImportContext {

  /**
   * The remote ID.
   *
   * @var string
   */
  protected $remoteId;

  /**
   * The channel ID.
   *
   * @var string
   */
  protected $channelId;

  /**
   * The config import ID.
   *
   * @var string
   */
  protected $importConfigId;

  /**
   * The list of entity UUIDs to import (optional).
   *
   * @var string[]
   */
  protected $uuids;

  /**
   * The offset on a JSON:API endpoint (optional).
   *
   * @var int
   */
  protected $offset;

  /**
   * The limit of entities to display on a JSON:API endpoint (optional).
   *
   * @var int
   */
  protected $limit;

  /**
   * The count of entities on the remote channel (optional).
   *
   * @var int
   */
  protected $remoteChannelCount;

  /**
   * Constructor.
   *
   * @param string $remote_id
   *   The remote ID.
   * @param string $channel_id
   *   The channel ID.
   * @param string $import_config_id
   *   The import config ID.
   */
  public function __construct($remote_id, $channel_id, $import_config_id) {
    $this->remoteId = $remote_id;
    $this->channelId = $channel_id;
    $this->importConfigId = $import_config_id;
  }

  /**
   * Get remote ID.
   *
   * @return string
   *   The remote ID.
   */
  public function getRemoteId() {
    return $this->remoteId;
  }

  /**
   * Get channel ID.
   *
   * @return string
   *   The channel ID.
   */
  public function getChannelId() {
    return $this->channelId;
  }

  /**
   * Get import config ID.
   *
   * @return string
   *   The import config ID.
   */
  public function getImportConfigId() {
    return $this->importConfigId;
  }

  /**
   * Get UUIDs.
   *
   * @return string[]
   *   The UUIDs.
   */
  public function getUuids() {
    return $this->uuids;
  }

  /**
   * Set UUIDs.
   *
   * @param string[] $uuids
   *   The UUIDs.
   */
  public function setUuids(array $uuids) {
    $this->uuids = $uuids;
  }

  /**
   * Get offset.
   *
   * @return int
   *   The offset.
   */
  public function getOffset() {
    return $this->offset;
  }

  /**
   * Set offset.
   *
   * @param int $offset
   *   The offset.
   */
  public function setOffset(int $offset) {
    $this->offset = $offset;
  }

  /**
   * Get limit.
   *
   * @return int
   *   The limit.
   */
  public function getLimit() {
    return $this->limit;
  }

  /**
   * Set limit.
   *
   * @param int $limit
   *   The limit.
   */
  public function setLimit(int $limit) {
    $this->limit = $limit;
  }

  /**
   * Get remote channel count.
   *
   * @return int
   *   The remote channel count.
   */
  public function getRemoteChannelCount() {
    return $this->remoteChannelCount;
  }

  /**
   * Set remote channel count.
   *
   * @param int $remote_channel_count
   *   The remote channel count.
   */
  public function setRemoteChannelCount(int $remote_channel_count) {
    $this->remoteChannelCount = $remote_channel_count;
  }

}
