<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_productivity_pack\Controller;

use Drupal\config_translation\Controller\ConfigTranslationController;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\Request;

if (class_exists(ConfigTranslationController::class)) {

  /**
   * Alters the page callback for the CKEditor 5 Templates translation overview page.
   */
  class TemplatesConfigTranslationController extends ConfigTranslationController {

    public function itemPage(Request $request, RouteMatchInterface $route_match, $plugin_id) {
      $page = parent::itemPage($request, $route_match, $plugin_id);
      foreach ($page['languages'] as $key => $item) {
        if (str_starts_with($key, '#')) {
          continue;
        }
        // Remove attributes - all are related to the "open in modal" functionality.
        if (isset($page['languages'][$key]['operations']['#links']['add']['attributes'])) {
          unset($page['languages'][$key]['operations']['#links']['add']['attributes']);
        }
        if (isset($page['languages'][$key]['operations']['#links']['edit']['attributes'])) {
          unset($page['languages'][$key]['operations']['#links']['edit']['attributes']);
        }
      }

      return $page;
    }
  }

}
