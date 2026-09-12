<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\Utility;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Adds functionality for retrieving the entity context data from the route.
 *
 * @todo To be removed if not needed anymore.
 */
trait RouteContextEntityTrait {

  /**
   * The route match if was declared.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface|null
   */
  protected ?RouteMatchInterface $routeMatch = NULL;

  /**
   * Gets the route context data.
   *
   * @return array
   *   The entity data (id, type).
   */
  public function getRouteContext(): array {
    $route_match = $this->routeMatch ?? \Drupal::routeMatch();

    $context = [
      'id' => NULL,
      'type' => NULL,
    ];

    foreach ($route_match->getParameters() as $parameter) {
      if (!$parameter instanceof EntityInterface) {
        continue;
      }

      $entity_type = $parameter->getEntityTypeId();
      $entity_route = implode('.', ['entity', $entity_type]);

      if (str_starts_with($route_match->getRouteName(), $entity_route)) {
        $context['id'] = $parameter->id();
        $context['type'] = $entity_type;

        return $context;
      }
    }

    return $context;
  }

}
