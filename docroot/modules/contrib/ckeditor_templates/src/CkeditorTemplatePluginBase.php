<?php

namespace Drupal\ckeditor_templates;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Extension\ModuleExtensionList;

/**
 * Base class for ckeditor_template plugins.
 */
abstract class CkeditorTemplatePluginBase extends PluginBase implements CkeditorTemplateInterface {

  /**
   * The path for the current module folder.
   *
   * @var string
   */
  protected string $moduleFolder;

  /**
   * Constructs a CkeditorTemplatePluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_list_module
   *   The provider for a list of available modules.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModuleExtensionList $extension_list_module) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->moduleFolder = '/' . $extension_list_module->getPath('ckeditor_templates');
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * Gets the thumbnail for an image.
   *
   * @return string
   *   The thumb image URL.
   */
  public function getThumb(): string {
    return $this->moduleFolder . '/js/ckeditor5_plugins/ckeditor_templates/theme/images/placeholder.svg';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->pluginDefinition['description'];
  }

}
