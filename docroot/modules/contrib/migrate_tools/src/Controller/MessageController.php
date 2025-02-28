<?php

namespace Drupal\migrate_tools\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate_plus\Entity\MigrationGroupInterface;
use Drupal\migrate_plus\Entity\MigrationInterface as MigratePlusMigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for migrate_tools message routes.
 */
class MessageController extends ControllerBase {

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Plugin manager for migration plugins.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('plugin.manager.migration')
    );
  }

  /**
   * Constructs a MessageController object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   A database connection.
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_plugin_manager
   *   The migration plugin manager.
   */
  public function __construct(Connection $database, MigrationPluginManagerInterface $migration_plugin_manager) {
    $this->database = $database;
    $this->migrationPluginManager = $migration_plugin_manager;
  }

  /**
   * Gets an array of log level classes.
   *
   * @return array
   *   An array of log level classes.
   */
  public static function getLogLevelClassMap() {
    return [
      MigrationInterface::MESSAGE_INFORMATIONAL => 'migrate-message-4',
      MigrationInterface::MESSAGE_NOTICE => 'migrate-message-3',
      MigrationInterface::MESSAGE_WARNING => 'migrate-message-2',
      MigrationInterface::MESSAGE_ERROR => 'migrate-message-1',
    ];
  }

  /**
   * Displays a listing of migration messages.
   *
   * Messages are truncated at 56 chars.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationGroupInterface $migration_group
   *   The migration group.
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The $migration.
   *
   * @return array
   *   A render array as expected by drupal_render().
   */
  public function overview(MigrationGroupInterface $migration_group, MigratePlusMigrationInterface $migration) {
    $rows = [];
    $classes = static::getLogLevelClassMap();
    $migration_plugin = $this->migrationPluginManager->createInstance($migration->id(), $migration->toArray());
    $source_id_field_names = array_keys($migration_plugin->getSourcePlugin()->getIds());
    $column_number = 1;
    foreach ($source_id_field_names as $source_id_field_name) {
      $header[] = [
        'data' => $source_id_field_name,
        'field' => 'sourceid' . $column_number++,
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ];
    }
    $header[] = [
      'data' => $this->t('Severity level'),
      'field' => 'level',
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];
    $header[] = [
      'data' => $this->t('Message'),
      'field' => 'message',
    ];
    $header[] = [
      'data' => $this->t('Status'),
      'field' => 'source_row_status',
    ];

    $result = [];
    $message_table = $migration_plugin->getIdMap()->messageTableName();
    if ($this->database->schema()->tableExists($message_table)) {
      $map_table = $migration_plugin->getIdMap()->mapTableName();
      $query = $this->database->select($message_table, 'msg')
        ->extend('\Drupal\Core\Database\Query\PagerSelectExtender')
        ->extend('\Drupal\Core\Database\Query\TableSortExtender');
      $query->innerJoin($map_table, 'map', 'msg.source_ids_hash=map.source_ids_hash');
      $query->fields('msg');
      $query->fields('map');
      $result = $query
        ->limit(50)
        ->orderByHeader($header)
        ->execute();
    }

    $status_strings = [
      MigrateIdMapInterface::STATUS_IMPORTED => $this->t('Imported'),
      MigrateIdMapInterface::STATUS_NEEDS_UPDATE => $this->t('Pending'),
      MigrateIdMapInterface::STATUS_IGNORED => $this->t('Ignored'),
      MigrateIdMapInterface::STATUS_FAILED => $this->t('Failed'),
    ];

    foreach ($result as $message_row) {
      $column_number = 1;
      foreach ($source_id_field_names as $source_id_field_name) {
        $column_name = 'sourceid' . $column_number++;
        $row[$column_name] = $message_row->$column_name;
      }
      $row['level'] = $message_row->level;
      $row['message'] = $message_row->message;
      $row['status'] = $status_strings[$message_row->source_row_status];
      $row['class'] = [
        Html::getClass('migrate-message-' . $message_row->level),
        $classes[$message_row->level],
      ];
      $rows[] = $row;
    }

    $build['message_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => ['id' => $message_table, 'class' => [$message_table]],
      '#empty' => $this->t('No messages for this migration.'),
    ];
    $build['message_pager'] = ['#type' => 'pager'];

    return $build;
  }

  /**
   * Get the title of the page.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationGroupInterface $migration_group
   *   The migration group.
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   *   The $migration.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translated title.
   */
  public function title(MigrationGroupInterface $migration_group, MigratePlusMigrationInterface $migration) {
    return $this->t(
      'Messages of %migration',
      ['%migration' => $migration->label()]
    );
  }

}
