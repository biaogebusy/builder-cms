<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_productivity_pack\EventSubscriber;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route subscriber.
 */
final class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a RouteSubscriber object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    if (!$this->moduleHandler->moduleExists('config_translation')) {
      return;
    }

    $alterations = [
      'entity.ckeditor5_template.config_translation_overview' => [
        'type' => '_controller',
        'value' => '\Drupal\ckeditor5_premium_features_productivity_pack\Controller\TemplatesConfigTranslationController::itemPage',
      ],
      'config_translation.item.add.entity.ckeditor5_template.edit_form' => [
        'type' => '_form',
        'value' => '\Drupal\ckeditor5_premium_features_productivity_pack\Form\TemplatesConfigTranslationAddForm',
      ],
      'config_translation.item.edit.entity.ckeditor5_template.edit_form' => [
        'type' => '_form',
        'value' => '\Drupal\ckeditor5_premium_features_productivity_pack\Form\TemplatesConfigTranslationEditForm',
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
