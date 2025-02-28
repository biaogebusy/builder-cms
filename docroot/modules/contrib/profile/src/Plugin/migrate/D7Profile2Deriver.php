<?php

namespace Drupal\profile\Plugin\migrate;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\Plugin\MigrationDeriverTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver for Drupal 7 Profile2 migrations based on Profile2 types.
 */
class D7Profile2Deriver extends DeriverBase implements ContainerDeriverInterface {

  use MigrationDeriverTrait;
  use StringTranslationTrait;

  /**
   * The migration plugin manager for loading other migration plugins.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationPluginManager;

  /**
   * The migration field discovery service.
   *
   * @var \Drupal\migrate_drupal\FieldDiscoveryInterface
   */
  protected $fieldDiscovery;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    $instance = new self();
    $instance->fieldDiscovery = $container->get('migrate_drupal.field_discovery');
    $instance->migrationPluginManager = $container->get('plugin.manager.migration');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $type_source_plugin = static::getSourcePlugin('d7_profile2_type');
    try {
      $type_source_plugin->checkRequirements();
    }
    catch (RequirementsException $e) {
      // If the d7_profile2_type requirements failed, that means we do not
      // have a Drupal source database configured - there is nothing to
      // generate.
      return $this->derivatives;
    }

    try {
      foreach ($type_source_plugin as $row) {
        $bundle = $row->getSourceProperty('type');
        $values = $base_plugin_definition;

        $values['label'] = $this->t('@label (@type)', [
          '@label' => $values['label'],
          '@type' => $row->getSourceProperty('name'),
        ]);
        $values['source']['bundle'] = $bundle;
        $values['destination']['default_bundle'] = $bundle;

        $migration = $this->migrationPluginManager->createStubMigration($values);
        $this->fieldDiscovery->addBundleFieldProcesses($migration, 'profile2', $bundle);
        $this->derivatives[$bundle] = $migration->getPluginDefinition();
      }
    }
    catch (DatabaseExceptionWrapper $e) {
      // Once we begin iterating the source plugin it is possible that the
      // source tables will not exist. This can happen when the
      // MigrationPluginManager gathers up the migration definitions but we do
      // not actually have a Drupal 7 source database.
    }

    return $this->derivatives;
  }

}
