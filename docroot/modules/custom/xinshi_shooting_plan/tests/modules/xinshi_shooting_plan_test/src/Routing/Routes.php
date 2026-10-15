<?php

namespace Drupal\xinshi_shooting_plan_test\Routing;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Yaml\Yaml;

/** Exercises the production route declarations without installing AI model providers. */
final class Routes {

  public function routes(): RouteCollection {
    $routes = new RouteCollection();
    foreach (Yaml::parseFile(dirname(__DIR__, 5) . '/xinshi_shooting_plan.routing.yml') as $name => $definition) {
      $routes->add($name, new Route($definition['path'], $definition['defaults'] ?? [],
        $definition['requirements'] ?? [], $definition['options'] ?? [], '', [], $definition['methods'] ?? []));
    }
    return $routes;
  }

}
