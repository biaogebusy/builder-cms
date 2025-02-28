<?php

namespace Drupal\fivestar;

use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Contains methods for managing votes.
 */
class WidgetManager {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new VoteManager object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * Returns an array of all widgets.
   *
   * @return array
   *   An associative array of widgets and their info.
   *
   * @see hook_fivestar_widgets()
   */
  public function getWidgets() {
    $widgets = &drupal_static(__FUNCTION__);
    if (isset($widgets)) {
      return $widgets;
    }

    $widgets = $this->moduleHandler->invokeAll('fivestar_widgets');
    // Invoke hook_fivestar_widgets_alter() to allow all modules to alter the
    // discovered widgets.
    $this->moduleHandler->alter('fivestar_widgets', $widgets);

    return $widgets;
  }

  /**
   * Returns a widget's info.
   *
   * @param string $widget_key
   *   The key of the target widget.
   *
   * @return array
   *   An array of widget info.
   *
   * @see hook_fivestar_widgets()
   */
  public function getWidgetInfo($widget_key) {
    $widgets_info = $this->getWidgets();
    return $widgets_info[$widget_key] ?? [];
  }

  /**
   * Returns the label for a given widget if it exists.
   *
   * @param string $widget_key
   *   The key of the target widget.
   *
   * @return string
   *   The widget label.
   */
  public function getWidgetLabel($widget_key) {
    if (!$widget_info = $this->getWidgetInfo($widget_key)) {
      return '';
    }

    return $widget_info['label'];
  }

  /**
   * Returns the library for a given widget if it exists.
   *
   * @param string $widget_key
   *   The key of the target widget.
   *
   * @return string
   *   The library name.
   */
  public function getWidgetLibrary($widget_key) {
    if (!$widget_info = $this->getWidgetInfo($widget_key)) {
      return '';
    }

    return $widget_info['library'];
  }

  /**
   * Returns an array of field options based on available widgets.
   *
   * @return array
   *   Associative array where the key is the option value and the value is the
   *   option label.
   */
  public function getWidgetsOptionSet() {
    $options = [];
    foreach ($this->getWidgets() as $widget_key => $widget_info) {
      $options[$widget_key] = $widget_info['label'];
    }
    return $options;
  }

}
