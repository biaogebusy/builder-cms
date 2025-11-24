<?php

namespace Drupal\admin_toolbar_tools\Plugin\Menu;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Menu\MenuLinkDefault;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a menu link plugins for configuration entities.
 */
class MenuLinkPlugin extends MenuLinkDefault {

  /**
   * The plugin represented in the menu link.
   *
   * @var array<string, mixed>
   *   The plugin definition.
   */
  protected $targetPluginDefinition;

  /**
   * Adds the target plugin definition to parent's container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array{field_definition: \Drupal\Core\Field\FieldDefinitionInterface, settings: array<string>, label: string, view_mode: string, third_party_settings: array<string>} $configuration
   *   The configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param array<string, array<string, string>> $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    // Inject the target plugin manager and get its definition.
    $instance->targetPluginDefinition = $container->get($plugin_definition['metadata']['plugin_manager'])
      ->getDefinition($plugin_definition['metadata']['plugin_id']);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    $title = $this->t('Missing');
    if (!empty($this->targetPluginDefinition['label'])) {
      if (!empty($this->pluginDefinition['metadata']['label_pattern'])) {
        $title = new FormattableMarkup($this->pluginDefinition['metadata']['label_pattern'], ['@label' => $this->targetPluginDefinition['label']]);
      }
      else {
        $title = $this->targetPluginDefinition['label'];
      }
    }
    return $title;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->targetPluginDefinition['description'] ?: parent::getDescription();
  }

}
