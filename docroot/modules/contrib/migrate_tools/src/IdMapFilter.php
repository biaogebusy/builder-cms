<?php

namespace Drupal\migrate_tools;

use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;

/**
 * Class to filter ID map by an ID list.
 */
class IdMapFilter extends \FilterIterator implements MigrateIdMapInterface {

  /**
   * List of specific source IDs to import.
   *
   * @var array
   */
  protected $idList;

  /**
   * IdMapFilter constructor.
   *
   * @param \Drupal\migrate\Plugin\MigrateIdMapInterface $id_map
   *   The ID map.
   * @param array $id_list
   *   The id list to use in the filter.
   */
  public function __construct(MigrateIdMapInterface $id_map, array $id_list) {
    parent::__construct($id_map);
    $this->idList = $id_list;
  }

  /**
   * {@inheritdoc}
   */
  public function accept() {
    // Row is included.
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    if (empty($this->idList) || in_array(array_values($this->currentSource()), $this->idList)) {
      return TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function saveIdMapping(Row $row, array $destination_id_values, $status = self::STATUS_IMPORTED, $rollback_action = self::ROLLBACK_DELETE) {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    $map->saveIdMapping($row, $destination_id_values, $status, $rollback_action);
  }

  /**
   * {@inheritdoc}
   */
  public function saveMessage(array $source_id_values, $message, $level = MigrationInterface::MESSAGE_ERROR) {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    $map->saveMessage($source_id_values, $message, $level);
  }

  /**
   * {@inheritdoc}
   */
  public function getMessages(array $source_id_values = [], $level = NULL) {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    return $map->getMessages($source_id_values, $level);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareUpdate() {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    $map->prepareUpdate();
  }

  /**
   * {@inheritdoc}
   */
  public function processedCount() {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    $map->processedCount();
  }

  /**
   * {@inheritdoc}
   */
  public function importedCount() {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    return $map->importedCount();
  }

  /**
   * {@inheritdoc}
   */
  public function updateCount() {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    return $map->updateCount();
  }

  /**
   * {@inheritdoc}
   */
  public function errorCount() {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    return $map->errorCount();
  }

  /**
   * {@inheritdoc}
   */
  public function messageCount() {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    return $map->messageCount();
  }

  /**
   * {@inheritdoc}
   */
  public function delete(array $source_id_values, $messages_only = FALSE) {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    $map->delete($source_id_values, $messages_only);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteDestination(array $destination_id_values) {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    $map->deleteDestination($destination_id_values);
  }

  /**
   * {@inheritdoc}
   */
  public function clearMessages() {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    $map->clearMessages();
  }

  /**
   * {@inheritdoc}
   */
  public function getRowBySource(array $source_id_values) {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    return $map->getRowBySource($source_id_values);
  }

  /**
   * {@inheritdoc}
   */
  public function getRowByDestination(array $destination_id_values) {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    return $map->getRowByDestination($destination_id_values);
  }

  /**
   * {@inheritdoc}
   */
  public function getRowsNeedingUpdate($count) {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    return $map->getRowsNeedingUpdate($count);
  }

  /**
   * {@inheritdoc}
   */
  public function lookupSourceId(array $destination_id_values) {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    return $map->lookupSourceId($destination_id_values);
  }

  /**
   * {@inheritdoc}
   */
  public function lookupDestinationIds(array $source_id_values) {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    return $map->lookupDestinationIds($source_id_values);
  }

  /**
   * {@inheritdoc}
   */
  public function currentDestination() {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    return $map->currentDestination();
  }

  /**
   * {@inheritdoc}
   */
  public function currentSource() {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    return $map->currentSource();
  }

  /**
   * {@inheritdoc}
   */
  public function destroy() {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    $map->destroy();
  }

  /**
   * {@inheritdoc}
   */
  public function getQualifiedMapTableName() {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    return $map->getQualifiedMapTableName();
  }

  /**
   * {@inheritdoc}
   */
  public function setMessage(MigrateMessageInterface $message) {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    $map->setMessage($message);
  }

  /**
   * {@inheritdoc}
   */
  public function setUpdate(array $source_id_values) {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    $map->setUpdate($source_id_values);
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    return $map->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    $map = $this->getInnerIterator();
    \assert($map instanceof MigrateIdMapInterface);
    return $map->getPluginDefinition();
  }

}
