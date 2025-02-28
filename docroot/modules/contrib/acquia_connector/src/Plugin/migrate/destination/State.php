<?php

namespace Drupal\acquia_connector\Plugin\migrate\destination;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Plugin\migrate\destination\DestinationBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The destination plugin for importing data into the state.
 *
 * See https://www.drupal.org/project/migrate_plus/issues/3060556
 *   Patch copied into the connector to allow for 'core only' migration to work.
 *
 * Available configuration keys:
 * - (string) state_prefix: an optional prefix for the keys in a state.
 *
 * @example
 * @code
 * id: custom_state_migration
 * label: 'State import'
 * source:
 *   plugin: embedded_data
 *   data_rows:
 *     -
 *       a: 1
 *       b: [1, 2]
 *       c:
 *         - d: 3
 *   ids:
 *     a:
 *       type: integer
 * process:
 *   my_state1: a
 *   my_state2: b
 *   my_state3: c/d
 * destination:
 *   plugin: state
 *   state_prefix: my_key
 * @endcode
 *
 * @code
 * assert(\Drupal::state()->get('my_key.my_state1') === 1);
 * assert(\Drupal::state()->get('my_key.my_state2') === [1, 2]);
 * @endcode
 *
 * Without setting "state_prefix" to "my_key" or simply omitting this
 * option, keys in the state will not have the "my_key." prefix.
 *
 * @MigrateDestination(
 *   id = "state",
 * )
 */
class State extends DestinationBase implements ContainerFactoryPluginInterface, ConfigurableInterface {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, StateInterface $state) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->setConfiguration($configuration);
    $this->state = $state;
    $this->supportsRollback = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration = NULL) {
    return new static($configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('state'));
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'state_names' => [
        'type' => 'string',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function fields(MigrationInterface $migration = NULL) {
    // State API doesn't use fields.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    $ids = [];
    foreach ($row->getDestination() as $key => $value) {
      $key = $this->configuration['state_prefix'] . $key;
      $this->state->set($key, $value);
      $ids[] = $key;
    }

    // Contrary to configuration entities, states can not be nested,
    // so every state must be stored separately from others.
    // To be able to migrate several states in one migrate source row,
    // combine their names and send as one ID.
    return [implode(',', $ids)];
  }

  /**
   * {@inheritdoc}
   */
  public function rollback(array $destination_identifier) {
    // Destination identifier is a comma-concatenated string of state names.
    // This identifier is the comma separated return value from ::import().
    foreach (explode(',', $destination_identifier['state_names']) as $key) {
      $this->state->delete($key);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'state_prefix' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeepArray([
      $this->defaultConfiguration(),
      $configuration,
    ], TRUE);
  }

}
