<?php

namespace Drupal\charts_api_example\Controller;

use Drupal\charts\ChartManager;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Directory of per-library Charts API examples.
 *
 * Each charts library can advertise an example page via the "example_route"
 * property on its Chart plugin attribute. This controller lists every enabled
 * library that does so, without hard-coding which libraries exist, so libraries
 * (and their example submodules) can be added or removed independently.
 */
class ChartsApiExample extends ControllerBase {

  /**
   * Constructs the controller.
   *
   * @param \Drupal\charts\ChartManager $chartManager
   *   The charts library plugin manager.
   * @param \Drupal\Core\Routing\RouteProviderInterface $routeProvider
   *   The route provider, used to skip libraries whose example module is off.
   */
  public function __construct(
    protected ChartManager $chartManager,
    protected RouteProviderInterface $routeProvider,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.charts'),
      $container->get('router.route_provider'),
    );
  }

  /**
   * Lists the available per-library API example pages.
   *
   * @return array
   *   A render array.
   */
  public function display(): array {
    $items = [];
    foreach ($this->chartManager->getDefinitions() as $definition) {
      $route = $definition['example_route'] ?? NULL;
      if (!$route || !$this->routeExists($route)) {
        continue;
      }
      $items[] = Link::fromTextAndUrl($definition['name'], Url::fromRoute($route))->toRenderable();
    }

    if (!$items) {
      return [
        '#markup' => $this->t('No charts library example pages are available. Enable a charts library and its API example submodule (for example, Charts Highcharts API Example) to see demonstrations of the Charts API.'),
      ];
    }

    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Charts API examples by library'),
      '#items' => $items,
      '#cache' => [
        // Rebuild when the set of installed modules/plugins changes.
        'tags' => ['charts_plugins'],
      ],
    ];
  }

  /**
   * Checks whether a route exists (its example module may not be enabled).
   *
   * @param string $route_name
   *   The route name.
   *
   * @return bool
   *   TRUE if the route is defined.
   */
  protected function routeExists(string $route_name): bool {
    try {
      $this->routeProvider->getRouteByName($route_name);
      return TRUE;
    }
    catch (RouteNotFoundException) {
      return FALSE;
    }
  }

}
