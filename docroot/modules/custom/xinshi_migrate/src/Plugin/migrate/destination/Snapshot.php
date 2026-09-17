<?php

namespace Drupal\xinshi_migrate\Plugin\migrate\destination;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateDestination;
use Drupal\migrate\Plugin\migrate\destination\DestinationBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\xinshi_migrate\SnapshotPlan;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Writes reviewed SQL rows without replaying entity-save business actions. */
#[MigrateDestination(id: 'xinshi_snapshot')]
final class Snapshot extends DestinationBase implements ContainerFactoryPluginInterface {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, private readonly SnapshotPlan $plan) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL): self {
    return new self($configuration, $plugin_id, $plugin_definition, $migration, $container->get('xinshi_migrate.plan'));
  }

  public function fields(): array {
    return ['record' => 'Complete source SQL record'];
  }

  public function getIds(): array {
    return $this->plan->table($this->configuration['table_name'])['ids'];
  }

  public function import(Row $row, array $old_destination_id_values = []): array {
    return $this->plan->write($this->configuration['table_name'], $row->getDestinationProperty('record'));
  }

  public function supportsRollback(): bool {
    return TRUE;
  }

  public function rollback(array $destination_identifier): void {
    $this->plan->rollback($this->configuration['table_name'], $destination_identifier);
  }

}
