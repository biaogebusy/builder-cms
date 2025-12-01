<?php

namespace Drupal\flood_control;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Provides Flood Unblock actions.
 */
class FloodUnblockManagerDatabase extends FloodUnblockManagerBase {

  /**
   * The Database Connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The Flood Service.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected $flood;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The Entity Type Manager Interface.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * FloodUnblockAdminForm constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood interface.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Config Factory Interface.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Entity Type Manager Interface.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The Messenger Interface.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    Connection $database,
    FloodInterface $flood,
    ConfigFactoryInterface $configFactory,
    EntityTypeManagerInterface $entityTypeManager,
    MessengerInterface $messenger,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->database = $database;
    $this->flood = $flood;
    $this->entityTypeManager = $entityTypeManager;
    $this->config = $configFactory->get('user.flood');
    $this->messenger = $messenger;
    $this->logger = $logger_factory->get('flood_control');
  }

  /**
   * {@inheritdoc}
   */
  public function canFilter() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function floodUnblockClearEvent($fid) {
    $txn = $this->database->startTransaction('flood_unblock_clear');
    try {
      $query = $this->database->delete('flood')
        ->condition('fid', $fid);
      $success = $query->execute();
      if ($success) {
        $this->messenger->addMessage($this->t('Flood entries cleared.'), 'status', FALSE);
      }
    }
    catch (\Exception $e) {
      // Something went wrong somewhere, so roll back now.
      $txn->rollback();

      // Log the exception to drupal.
      $this->logger->error($e);
      $this->messenger->addMessage($this->t('Error: @error', ['@error' => (string) $e]), 'error');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getEntries($limit = 50, $identifier = '', $header = []) {
    $query = $this->database->select('flood', 'f')
      ->extend('Drupal\Core\Database\Query\TableSortExtender')
      ->orderByHeader($header);
    $query->fields('f');
    if ($identifier) {
      $query->condition('identifier', "%" . $this->database->escapeLike($identifier) . "%", 'LIKE');
    }
    $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit($limit);
    $execute = $pager->execute();
    $results = $execute->fetchAll();
    $results_identifiers = array_column($results, 'identifier', 'fid');
    return [
      'results' => $results,
      'result_identifiers' => $results_identifiers,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getEventIds($event, $identifier = NULL) {
    $event_ids = [];
    $query = $this->database->select('flood', 'f');
    $query->condition('event', $event);
    if ($identifier) {
      $query->condition('f.identifier', $identifier, 'LIKE');
    }
    $query->fields('f', ['fid']);
    $result = $query->execute();
    foreach ($result as $record) {
      $event_ids[] = $record->fid;
    }
    return $event_ids;
  }

}
