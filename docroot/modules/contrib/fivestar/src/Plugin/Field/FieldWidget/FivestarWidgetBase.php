<?php

namespace Drupal\fivestar\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\fivestar\WidgetManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Fivestar field widgets.
 */
abstract class FivestarWidgetBase extends WidgetBase {

  /**
   * The Fivestar widget manager.
   *
   * @var \Drupal\fivestar\WidgetManager
   */
  protected $widgetManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs the FivestarWidgetBase object.
   *
   * @param string $plugin_id
   *   The plugin ID for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\fivestar\WidgetManager $widget_manager
   *   The widget manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, WidgetManager $widget_manager, RendererInterface $renderer) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->widgetManager = $widget_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('fivestar.widget_manager'),
      $container->get('renderer')
    );
  }

  /**
   * Gets the selected widget key.
   *
   * Sites that used an older version of the module will have a stale key set
   * for their selected widget. This returns the proper, cleaned up version
   * if that's the case.
   *
   * @return string
   *   The active widget's key
   */
  protected function getSelectedWidgetKey() {
    $setting = $this->getSetting('fivestar_widget') ?: 'default';
    if (strpos($setting, '.css') === FALSE) {
      return $setting;
    }

    $file_name = basename($setting);
    $file_name_exploded = explode('.', $file_name);
    $setting = reset($file_name_exploded);
    return $setting;
  }

}
