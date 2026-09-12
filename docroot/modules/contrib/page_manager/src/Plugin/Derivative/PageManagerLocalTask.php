<?php

namespace Drupal\page_manager\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local tasks for Page Manager pages.
 */
class PageManagerLocalTask extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a PageManagerLocalTask.
   *
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(RouteProviderInterface $route_provider, EntityTypeManagerInterface $entity_type_manager) {
    $this->routeProvider = $route_provider;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('router.route_provider'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];
    foreach ($this->gatherLocalTasks() as $plugin_id => $local_task) {
      try {
        $route_name = Url::fromUri('internal:' . $local_task['path'])->getRouteName();
      }
      catch (\UnexpectedValueException $e) {
        continue;
      }

      $this->derivatives[$plugin_id] = [
        'route_name' => $route_name,
        'title' => $local_task['title'],
        'weight' => $local_task['weight'] ?? 0,
      ] + $base_plugin_definition;

      // Set the parent_id if we know it at this point.
      if (isset($local_task['parent_id'])) {
        $this->derivatives[$plugin_id]['parent_id'] = $local_task['parent_id'];
      }
      // Default local tasks have themselves as root tab.
      elseif ($local_task['type'] === 'default') {
        $this->derivatives[$plugin_id]['base_route'] = $route_name;
      }
    }
    return $this->derivatives;
  }

  /**
   * Alters base_route and parent_id into the page local tasks.
   */
  public function alterLocalTasks(&$local_tasks) {
    // Build up a map from route name, to the parent id on any sub-tabs.
    $route_to_parent_map = [];
    foreach ($local_tasks as $plugin_id => $local_task) {
      if (!empty($local_task['route_name']) && !empty($local_task['parent_id'])) {
        $route_to_parent_map[$local_task['route_name']][] = $local_task['parent_id'];
      }
    }

    foreach ($this->gatherLocalTasks() as $plugin_id => $local_task) {
      $full_plugin_id = 'page_manager_page:' . $plugin_id;

      // We already have set the base_route for default tabs.
      if ($local_task['type'] === 'normal') {
        // The local task might not exist if we couldn't find a route for it in
        // generateDerivativeDefinitions().
        if (!isset($local_tasks[$full_plugin_id])) {
          continue;
        }

        // Find out the parent route.
        // @todo Find out how to find both the root and parent tab.
        $path = $local_task['path'];
        $split = explode('/', $path);
        array_pop($split);
        $path = implode('/', $split);

        $pattern = str_replace('%', '{}', $path);
        if ($routes = $this->routeProvider->getRoutesByPattern($pattern)) {
          foreach ($routes->all() as $name => $route) {
            // If the parent page already has a tab, then this is a sub-tab.
            if (isset($route_to_parent_map[$name])) {
              foreach ($route_to_parent_map[$name] as $parent_id) {
                if ($parent_id !== $full_plugin_id) {
                  $local_tasks[$full_plugin_id]['parent_id'] = $parent_id;
                  break;
                }
              }
            }

            if (empty($local_tasks[$full_plugin_id]['plugin_id'])) {
              $local_tasks[$full_plugin_id]['base_route'] = $name;
            }

            // Skip after the first found route.
            break;
          }
        }
      }
    }
  }

  /**
   * Gathers all the local tasks for pages and (if configured) their parents.
   *
   * @return array
   *   An associative array, keyed by the plugin id, with associative arrays
   *   as the values, with the following keys:
   *   - 'type': (string) Either "normal" or "default".
   *   - 'path': (string) The path.
   *   - 'title': (string) The title.
   *   - 'weight': (int) The weight.
   */
  protected function gatherLocalTasks() {
    $local_tasks = [];
    foreach ($this->getApplicablePages() as $page_id) {
      /** @var \Drupal\page_manager\PageInterface $page */
      $page = $this->entityTypeManager->getStorage('page')->load($page_id);
      if (!$page || !$page->status()) {
        continue;
      }

      $menu_type = $page->getMenuType();
      $menu_settings = $page->getMenuSettings();

      $path = $page->getPath();
      $plugin_id = 'page_manager.page_tab_' . $page_id;

      // Add the local task itself.
      $local_tasks[$plugin_id] = [
        'path' => $path,
        'title' => $menu_settings['title'] ?? '',
        'weight' => (int) ($menu_settings['weight'] ?? 0),
        'type' => $menu_type === 'default_menu_tab' ? 'default' : 'normal',
      ];

      // A default local task can also provide the tab it sits under.
      if ($menu_type === 'default_menu_tab' && ($menu_settings['parent_menu_type'] ?? 'none') === 'task') {
        $local_tasks[$plugin_id . '.parent'] = [
          'path' => $path,
          'title' => $menu_settings['parent_title'] ?? '',
          'weight' => (int) ($menu_settings['parent_weight'] ?? 0),
          'type' => 'normal',
        ];

        // Set the parent id for default tabs.
        $local_tasks[$plugin_id]['parent_id'] = 'page_manager_page:' . $plugin_id . '.parent';
      }
    }
    return $local_tasks;
  }

  /**
   * Gets the page ids of pages that provide a local task.
   *
   * @return string[]
   *   The page IDs.
   */
  public function getApplicablePages() {
    return $this->entityTypeManager->getStorage('page')->getQuery()
      ->accessCheck(FALSE)
      ->condition('menu_type', ['default_menu_tab', 'menu_tab'], 'IN')
      ->execute();
  }

}
