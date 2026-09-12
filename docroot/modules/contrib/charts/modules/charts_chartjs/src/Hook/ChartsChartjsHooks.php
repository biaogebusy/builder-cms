<?php

declare(strict_types=1);

namespace Drupal\charts_chartjs\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for the Charts Chartjs module.
 */
class ChartsChartjsHooks {

  /**
   * Constructs a new ChartsChartjsHooks object.
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
    $is_config = $for === 'config';
    $chartjs_config = $is_config ? $this->configFactory->getEditable('charts_chartjs.settings') : NULL;
    if (!$is_config || !$chartjs_config || empty($new_settings['library']) || $new_settings['library'] !== 'chartjs') {
      if ($chartjs_config) {
        $chartjs_config->delete();
      }
      return;
    }

    $new_settings['library_config'] = [
      'xaxis' => [
        'autoskip' => TRUE,
        'horizontal_axis_title_align' => 'start',
      ],
      'yaxis' => [
        'vertical_axis_title_align' => 'start',
      ],
    ];
    $chartjs_config->delete();
  }

}
