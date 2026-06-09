<?php

namespace Drupal\ai_prompt_test\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Modify AI Content Suggestions route permission.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {

    // Change permission for AI Content Suggestions to 'manage ai prompts' in
    // order to test access within the AI Prompt Element without having to build
    // a custom form with AI Element.
    if ($route = $collection->get('ai_content_suggestions.settings')) {
      $route->setRequirement('_permission', 'manage ai prompts');
    }
  }

}
