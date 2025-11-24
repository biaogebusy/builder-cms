<?php

namespace Drupal\eck\Plugin\migrate;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\Plugin\MigrationDeriverTrait;
use Drupal\migrate_drupal\FieldDiscoveryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver for Drupal 7 ECK.
 *
 * @see \Drupal\eck\Plugin\migrate\source\d7\EckEntity::query
 * @see \Drupal\eck\Plugin\migrate\source\d7\EckEntityTranslation::query
 *
 * @see \Drupal\node\Plugin\migrate\D7NodeDeriver
 */
class D7EckDeriver extends DeriverBase implements ContainerDeriverInterface {

  use MigrationDeriverTrait;

  /**
   * The base plugin ID this derivative is for.
   *
   * @var string
   */
  protected $basePluginId;

  /**
   * The migration field discovery service.
   *
   * @var \Drupal\migrate_drupal\FieldDiscoveryInterface
   */
  protected $fieldDiscovery;

  /**
   * D7EckDeriver constructor.
   *
   * @param string $base_plugin_id
   *   The base plugin ID for the plugin ID.
   * @param bool $translations
   *   Whether or not to include translations.
   * @param \Drupal\migrate_drupal\FieldDiscoveryInterface $field_discovery
   *   The migration field discovery service.
   */
  public function __construct($base_plugin_id, $translations, FieldDiscoveryInterface $field_discovery) {
    $this->basePluginId = $base_plugin_id;
    $this->includeTranslations = $translations;
    $this->fieldDiscovery = $field_discovery;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    // Translations don't make sense unless we have content_translation.
    return new static(
      $base_plugin_id,
      $container->get('module_handler')->moduleExists('content_translation'),
      $container->get('migrate_drupal.field_discovery')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $eck_types = static::getSourcePlugin('d7_eck_entity_type');
    $eck_bundles = static::getSourcePlugin('d7_eck_entity_bundle');

    try {
      $eck_types->checkRequirements();
      $eck_bundles->checkRequirements();
    }
    catch (RequirementsException $e) {
      // If the requirements failed, there is nothing to generate.
      return $this->derivatives;
    }

    try {
      foreach ($eck_types as $type_row) {
        $entity_type = $type_row->getSourceProperty('name');

        foreach (static::getSourcePlugin('d7_eck_entity_bundle') as $bundle_row) {
          $bundle = $bundle_row->getSourceProperty('name');
          $bundle_entity_type = $bundle_row->getSourceProperty('entity_type');

          // Not for this entity type.
          if ($bundle_entity_type != $entity_type) {
            continue;
          }

          $values = $base_plugin_definition;
          $values['label'] = t('@label (@type)', [
            '@label' => $bundle_row->getSourceProperty('label'),
            '@type' => $type_row->getSourceProperty('name'),
          ]);
          $values['source']['entity_type'] = $entity_type;
          $values['source']['bundle'] = $bundle;

          $values['destination']['plugin'] = "entity:{$entity_type}";
          $values['destination']['default_bundle'] = $bundle;

          /** @var \Drupal\migrate\Plugin\Migration $migration */
          $migration = \Drupal::service('plugin.manager.migration')->createStubMigration($values);
          $source_plugin = $migration->getSourcePlugin();
          $source_plugin->rewind();
          /** @var \Drupal\migrate\Row $row */
          $row = $source_plugin->current();
          if (!$row) {
            continue;
          }
          $this->fieldDiscovery->addBundleFieldProcesses($migration, $entity_type, $bundle);
          $this->derivatives[$entity_type . ':' . $bundle] = $migration->getPluginDefinition();
        }
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
