<?php

namespace Drupal\Tests\page_manager\Functional;

/**
 * Provides helpers for Page Manager tests.
 */
trait PageTestHelperTrait {

  // @fixme: Remove this change when https://www.drupal.org/node/2684281 is fixed.

  /**
   * Triggers a router rebuild.
   *
   * The UI would trigger a router rebuild, call it manually for API calls.
   */
  protected function triggerRouterRebuild() {
    $this->container->get('router.builder')->rebuildIfNeeded();
  }

}
