<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_plugin_pack_templates\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route subscriber.
 */
final class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $alterations = [
      'entity.ckeditor5_template.config_translation_overview' => [
        'type' => '_controller',
        'value' => '\Drupal\ckeditor5_plugin_pack_templates\Controller\TemplatesConfigTranslationController::itemPage',
      ],
      'config_translation.item.add.entity.ckeditor5_template.edit_form' => [
        'type' => '_form',
        'value' => '\Drupal\ckeditor5_plugin_pack_templates\Form\TemplatesConfigTranslationAddForm',
      ],
      'config_translation.item.edit.entity.ckeditor5_template.edit_form' => [
        'type' => '_form',
        'value' => '\Drupal\ckeditor5_plugin_pack_templates\Form\TemplatesConfigTranslationEditForm',
      ],
    ];

    foreach ($alterations as $route_name => $alteration) {
      $route = $collection->get($route_name);
      if ($route) {
        $route->setDefault($alteration['type'], $alteration['value']);
        $collection->remove($route_name);
        $collection->add($route_name, $route);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Come after config_translation.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -120];
    return $events;
  }

}
