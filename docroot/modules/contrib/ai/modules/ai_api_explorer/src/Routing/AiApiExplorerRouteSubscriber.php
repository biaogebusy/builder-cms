<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\ai_api_explorer\AiApiExplorerPluginManager;
use Drupal\ai_api_explorer\Form\AiApiExplorerForm;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route subscriber.
 */
final class AiApiExplorerRouteSubscriber extends RouteSubscriberBase {

  /**
   * Constructs an AiApiExplorerRouteSubscriber object.
   */
  public function __construct(
    private readonly AiApiExplorerPluginManager $pluginManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach ($this->pluginManager->getDefinitions() as $plugin_id => $definition) {
      if ($plugin = $this->pluginManager->createInstance($plugin_id, $definition)) {
        $string_title = $plugin->getLabel()->__toString();

        $route = new Route('/admin/config/ai/explorers/' . $plugin_id);
        $route
          ->setDefaults([
            '_title' => $string_title,
            '_form' => AiApiExplorerForm::class,
            'plugin_id' => $plugin_id,
          ])
          ->setOption('_admin_route', TRUE)
          ->setRequirement('_ai_api_explorer_access', 'TRUE');

        $collection->add('ai_api_explorer.form.' . $plugin_id, $route);
      }
    }
  }

}
