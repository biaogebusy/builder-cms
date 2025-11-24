<?php

declare(strict_types=1);

namespace Drupal\diff;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\diff\Annotation\FieldDiffBuilder as FieldDiffBuilderAnnotation;
use Drupal\diff\Attribute\FieldDiffBuilder as FieldDiffBuilderAttribute;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Plugin type manager for field diff builders.
 *
 * @ingroup field_diff_builder
 *
 * Plugin directory Plugin/diff/Field.
 *
 * @see \Drupal\diff\Annotation\FieldDiffBuilder
 * @see \Drupal\diff\FieldDiffBuilderInterface
 * @see plugin_api
 */
class DiffBuilderManager extends DefaultPluginManager {

  protected ImmutableConfig $config;
  protected ImmutableConfig $pluginsConfig;
  protected array $pluginDefinitions;

  /**
   * Constructs a DiffBuilderManager object.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
    protected EntityTypeManagerInterface $entityTypeManager,
    ConfigFactoryInterface $config_factory,
  ) {
    parent::__construct('Plugin/diff/Field', $namespaces, $module_handler, FieldDiffBuilderInterface::class, FieldDiffBuilderAttribute::class, FieldDiffBuilderAnnotation::class);

    $this->setCacheBackend($cache_backend, 'field_diff_builder_plugins');
    $this->alterInfo('field_diff_builder_info');
    $this->config = $config_factory->get('diff.settings');
    $this->pluginsConfig = $config_factory->get('diff.plugins');
  }

  /**
   * Define whether a field should be displayed or not as a diff change.
   *
   * To define if a field should be displayed in the diff comparison, check if
   * it is revisionable and is not the bundle or revision field of the entity.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_storage_definition
   *   The field storage definition.
   *
   * @return bool
   *   TRUE if the field will be displayed.
   */
  public function showDiff(FieldStorageDefinitionInterface $field_storage_definition): bool {
    $show_diff = FALSE;
    // Check if the field is revisionable.
    if ($field_storage_definition->isRevisionable()) {
      $show_diff = TRUE;
      // Do not display the field, if it is the bundle or revision field of the
      // entity.
      $entity_type = $this->entityTypeManager->getDefinition($field_storage_definition->getTargetEntityTypeId());
      // @todo Don't hard code fields after: https://www.drupal.org/node/2248983
      if (\in_array($field_storage_definition->getName(), [
        'revision_log',
        'revision_uid',
        $entity_type->getKey('bundle'),
        $entity_type->getKey('revision'),
      ])) {
        $show_diff = FALSE;
      }
    }
    return $show_diff;
  }

  /**
   * Creates a plugin instance for a field definition.
   *
   * Creates the instance based on the selected plugin for the field.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   *
   * @return \Drupal\diff\FieldDiffBuilderInterface|null
   *   The plugin instance, NULL if none.
   */
  public function createInstanceForFieldDefinition(FieldDefinitionInterface $field_definition): ?FieldDiffBuilderInterface {
    $selected_plugin = $this->getSelectedPluginForFieldStorageDefinition($field_definition->getFieldStorageDefinition());
    if ($selected_plugin['type'] != 'hidden') {
      return $this->createInstance($selected_plugin['type'], $selected_plugin['settings']);
    }
    return NULL;
  }

  /**
   * Selects a default plugin for a field storage definition.
   *
   * Checks if a plugin has been already selected for the field, otherwise
   * chooses one between the plugins that can be applied to the field.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_definition
   *   The field storage definition.
   *
   * @return array
   *   An array with the key type (which contains the plugin ID) and settings.
   *   The special type hidden indicates that the field should not be shown.
   */
  public function getSelectedPluginForFieldStorageDefinition(FieldStorageDefinitionInterface $field_definition): array {
    $plugin_options = $this->getApplicablePluginOptions($field_definition);
    $field_key = $field_definition->getTargetEntityTypeId() . '.' . $field_definition->getName();

    // Start with the stored configuration, this returns NULL if there is none.
    $selected_plugin = $this->pluginsConfig->get('fields.' . $field_key);

    // If there is configuration and it is a valid type or explicitly set to
    // hidden, then use that, otherwise try to find a suitable default plugin.
    if ($selected_plugin && (\in_array($selected_plugin['type'], \array_keys($plugin_options)) || $selected_plugin['type'] == 'hidden')) {
      return $selected_plugin + ['settings' => []];
    }
    elseif (!empty($plugin_options) && $this->isFieldStorageDefinitionDisplayed($field_definition)) {
      return ['type' => \key($plugin_options), 'settings' => []];
    }
    else {
      return ['type' => 'hidden', 'settings' => []];
    }
  }

  /**
   * Determines if the field is displayed.
   *
   * Determines if a field should be displayed when comparing revisions based on
   * the entity view display if there is no plugin selected for the field.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_storage_definition
   *   The field name.
   *
   * @return bool
   *   Whether the field is displayed.
   */
  public function isFieldStorageDefinitionDisplayed(FieldStorageDefinitionInterface $field_storage_definition): bool {
    if (($field_storage_definition instanceof BaseFieldDefinition && $field_storage_definition->isDisplayConfigurable('view')) || $field_storage_definition instanceof FieldStorageConfig) {
      $field_key = 'content.' . $field_storage_definition->getName() . '.type';
      $storage = $this->entityTypeManager->getStorage('entity_view_display');
      $query = $storage->getQuery()->condition('targetEntityType', $field_storage_definition->getTargetEntityTypeId());
      if ($field_storage_definition instanceof FieldStorageConfig) {
        $bundles = $field_storage_definition->getBundles();
        $query->condition('bundle', (array) $bundles, 'IN');
      }
      $result = $query->exists($field_key)->range(0, 1)->execute();
      return !empty($result) ? TRUE : FALSE;
    }
    else {
      $view_options = (bool) $field_storage_definition->getDisplayOptions('view');
      return $view_options;
    }
  }

  /**
   * Gets the applicable plugin options for a given field.
   *
   * Loop over the plugins that can be applied to the field and builds an array
   * of possible plugins based on each plugin weight.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $field_definition
   *   The field storage definition.
   *
   * @return array
   *   The plugin option for the given field based on plugin weight.
   */
  public function getApplicablePluginOptions(FieldStorageDefinitionInterface $field_definition): array {
    $plugins = $this->getPluginDefinitions();
    // Build a list of all diff plugins supporting the field type of the field.
    $plugin_options = [];
    if (isset($plugins[$field_definition->getType()])) {
      // Sort the plugins based on their weight.
      \uasort($plugins[$field_definition->getType()], 'Drupal\Component\Utility\SortArray::sortByWeightElement');

      foreach ($plugins[$field_definition->getType()] as $id => $weight) {
        $definition = $this->getDefinition($id, FALSE);
        // Check if the plugin is applicable.
        if (isset($definition['class']) && \in_array($field_definition->getType(), $definition['field_types'])) {
          /** @var \Drupal\diff\FieldDiffBuilderInterface $class */
          $class = $definition['class'];
          if ($class::isApplicable($field_definition)) {
            $plugin_options[$id] = $this->getDefinitions()[$id]['label'];
          }
        }
      }
    }
    return $plugin_options;
  }

  /**
   * Initializes the local pluginDefinitions property.
   *
   * Loop over the plugin definitions and build an array keyed by the field type
   * that plugins can be applied to.
   *
   * @return array
   *   The initialized plugins array sort by field type.
   */
  public function getPluginDefinitions(): array {
    if (!isset($this->pluginDefinitions)) {
      // Get the definition of all the FieldDiffBuilder plugins.
      foreach ($this->getDefinitions() as $plugin_definition) {
        if (isset($plugin_definition['field_types'])) {
          // Iterate through all the field types this plugin supports
          // and for every such field type add the id of the plugin.
          if (!isset($plugin_definition['weight'])) {
            $plugin_definition['weight'] = 0;
          }

          foreach ($plugin_definition['field_types'] as $id) {
            $this->pluginDefinitions[$id][$plugin_definition['id']]['weight'] = $plugin_definition['weight'];
          }
        }
      }
    }
    return $this->pluginDefinitions;
  }

  /**
   * Clear the pluginDefinitions local property array.
   */
  public function clearCachedDefinitions(): void {
    unset($this->pluginDefinitions);
  }

}
