<?php

namespace Drupal\queue_ui\Plugin\QueueUI;

use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\DatabaseQueue as CoreDatabaseQueue;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\queue_ui\QueueUIBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the default Drupal Queue UI backend.
 *
 * @phpstan-consistent-constructor
 * @QueueUI(
 *   id = "database_queue",
 *   class_name = "DatabaseQueue"
 * )
 */
class DatabaseQueue extends QueueUIBase implements ContainerFactoryPluginInterface {
  use StringTranslationTrait;

  /**
   * The database table name.
   */
  public const TABLE_NAME = CoreDatabaseQueue::TABLE_NAME;

  /**
   * Database.
   *
   * @var \Drupal\Core\Database\Database
   * It acts to encapsulate all control
   */
  protected $database;

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The current service container.
   * @param array $configuration
   *   The configuration to use.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return \Drupal\Core\Plugin\ContainerFactoryPluginInterface|\Drupal\queue_ui\Plugin\QueueUI\DatabaseQueue
   *   Defines the default Drupal Queue UI backend
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database')
    );
  }

  /**
   * DatabaseQueue constructor.
   *
   * @param array $configuration
   *   The configuration to use.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $database) {
    $this->database = $database;
  }

  /**
   * DatabaseQueue implements all default QueueUI methods.
   *
   * @return array
   *   An array of available QueueUI methods. Array key is system name of the
   *   operation, array key value is the display name.
   */
  public function getOperations() {
    return [
      'view' => $this->t('View'),
      'release' => $this->t('Release'),
      'delete' => $this->t('Delete'),
    ];
  }

  /**
   * Inspect the queue items in a specified queue.
   *
   * @param string $queueName
   *   The name of the queue being inspected.
   *
   * @return mixed
   *   Return call the execute method.
   */
  public function getItems($queueName) {
    $query = $this->database->select(static::TABLE_NAME, 'q');
    $query->addField('q', 'item_id');
    $query->addField('q', 'expire');
    $query->addField('q', 'created');
    $query->condition('q.name', $queueName);
    $query = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender');
    $query = $query->limit(25);

    return $query->execute();
  }

  /**
   * Force the releasing of a queue.
   *
   * @param string $queueName
   *   The name of the queue being inspected.
   *
   * @return \Drupal\Core\Database\StatementInterface|int|null
   *   return the value null
   */
  public function releaseItems($queueName) {
    return $this->database->update(static::TABLE_NAME)
      ->fields([
        'expire' => 0,
      ])
      ->condition('name', $queueName, '=')
      ->execute();
  }

  /**
   * Load a specified SystemQueue queue item from the database.
   *
   * @param int $item_id
   *   The item id to load.
   *
   * @return mixed
   *   Result of the database query loading the queue item.
   */
  public function loadItem($item_id) {
    // Load the specified queue item from the queue table.
    $query = $this->database->select(static::TABLE_NAME, 'q')
      ->fields('q', ['item_id', 'name', 'data', 'expire', 'created'])
      ->condition('q.item_id', $item_id)
    // Item id should be unique.
      ->range(0, 1);

    return $query->execute()->fetchObject();
  }

  /**
   * Force the releasing of a specified queue item.
   *
   * @param int $item_id
   *   The item id to be released.
   */
  public function releaseItem($item_id) {
    $this->database->update(static::TABLE_NAME)
      ->condition('item_id', $item_id)
      ->fields(['expire' => 0])
      ->execute();
  }

  /**
   * Force the deletion of a specified queue item.
   *
   * @param int $item_id
   *   The item id to be deleted.
   */
  public function deleteItem($item_id) {
    $this->database->delete(static::TABLE_NAME)
      ->condition('item_id', $item_id)
      ->execute();
  }

}
