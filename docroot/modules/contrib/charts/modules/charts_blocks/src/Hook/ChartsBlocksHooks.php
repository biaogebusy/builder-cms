<?php

declare(strict_types=1);

namespace Drupal\charts_blocks\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for the Charts Blocks module.
 */
class ChartsBlocksHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   *
   * @param string $route_name
   *   The route name.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return string
   *   The help HTML.
   *
   * @phpstan-ignore-next-line
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): string {
    $output = '';
    switch ($route_name) {
      // Help for the charts_api_example module.
      case 'help.page.charts_blocks':
        $output .= '<h3>' . $this->t('About') . '</h3>';
        $output .= '<p>' . $this->t('Create Charts blocks without the need for Views.') . '</p>';
        return $output;
    }
    return $output;
  }

}
