<?php

declare(strict_types=1);

namespace Drupal\charts_c3\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the Charts C3 module.
 */
class ChartsC3Hooks {

  /**
   * Constructs a new ChartsC3Hooks object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(protected ConfigFactoryInterface $configFactory) {
  }

  /**
   * Implements hook_charts_version3_to_new_settings_structure_alter().
   *
   * @param array $new_settings
   *   The new settings.
   * @param string $for
   *   What this is supposed to affect.
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('charts_version3_to_new_settings_structure_alter')]
  public function chartsVersion3ToNewSettingsStructureAlter(array &$new_settings, string $for): void {
    $c3_config = $this->configFactory->getEditable('charts_c3.settings');
    if ($c3_config) {
      $c3_config->delete();
    }
  }

}
