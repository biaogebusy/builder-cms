<?php

declare(strict_types=1);

namespace Drupal\elasticsearch_connector\Analyser;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines an interface for analyser plugins.
 */
interface AnalyserInterface extends PluginFormInterface, ConfigurableInterface, PluginInspectionInterface {

  /**
   * Gets the analyser label.
   *
   * @return string
   *   The label.
   */
  public function getLabel(): string;

  /**
   * Gets the analyser settings.
   *
   * @return array
   *   Analyser settings.
   */
  public function getSettings(): array;

}
