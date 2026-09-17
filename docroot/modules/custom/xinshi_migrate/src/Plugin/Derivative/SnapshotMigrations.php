<?php

namespace Drupal\xinshi_migrate\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\xinshi_migrate\SnapshotPlan;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Creates migrations only from the explicitly prepared private batch plan. */
final class SnapshotMigrations extends DeriverBase implements ContainerDeriverInterface {

  public function __construct(private readonly SnapshotPlan $plan) {}

  public static function create(ContainerInterface $container, $base_plugin_id): self {
    return new self($container->get('xinshi_migrate.plan'));
  }

  public function getDerivativeDefinitions($base_plugin_definition): array {
    $previous = NULL;
    foreach ($this->plan->tables() as $table => $specification) {
      $definition = $base_plugin_definition;
      $definition['label'] = 'Snapshot: ' . $table;
      $definition['source']['table_name'] = $table;
      $definition['source']['key'] = $this->plan->sourceKey();
      $definition['destination']['table_name'] = $table;
      $definition['migration_dependencies']['required'] = $previous ? [$previous] : [];
      $this->derivatives[$table] = $definition;
      $previous = 'xinshi_snapshot:' . $table;
    }
    return $this->derivatives;
  }

}
