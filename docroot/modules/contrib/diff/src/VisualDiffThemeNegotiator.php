<?php

declare(strict_types=1);

namespace Drupal\diff;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeNegotiatorInterface;

/**
 * Visual inline layout theme negotiator.
 *
 * @package Drupal\diff
 */
class VisualDiffThemeNegotiator implements ThemeNegotiatorInterface {

  /**
   * VisualDiffThemeNegotiator constructor.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $routeMatch) {
    if ($routeMatch->getParameter('filter') !== 'visual_inline') {
      return FALSE;
    }

    if (!$this->isDiffRoute($routeMatch)) {
      return FALSE;
    }

    if ($this->configFactory->get('diff.settings')->get('general_settings.visual_inline_theme') !== 'default') {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function determineActiveTheme(RouteMatchInterface $route_match) {
    return $this->configFactory->get('system.theme')->get('default');
  }

  /**
   * Checks if route names for node or other entity are corresponding.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Route match object.
   *
   * @return bool
   *   Return TRUE if route name is ok.
   */
  protected function isDiffRoute(RouteMatchInterface $route_match): bool {
    $regex_pattern = '/^entity\..*\.revisions_diff$/';
    return $route_match->getRouteName() === 'diff.revisions_diff' ||
      \preg_match($regex_pattern, (string) $route_match->getRouteName());
  }

}
