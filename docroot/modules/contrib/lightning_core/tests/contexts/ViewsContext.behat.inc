<?php

namespace Acquia\LightningExtension\Context;

use Drupal\DrupalExtension\Context\DrupalSubContextBase;

/**
 * Contains step definitions for working with views.
 *
 * @internal
 *   This is an internal part of Lightning Core's testing system and may be
 *   changed or removed at any time without warning. It should not be extended,
 *   instantiated, or used in any way by external code! If you need to use this
 *   functionality, you should copy the relevant code into your own project.
 */
final class ViewsContext extends DrupalSubContextBase {

  use AwaitTrait;

  /**
   * Submits exposed Views filters.
   *
   * @When I apply the exposed filters
   */
  public function applyExposedFilters() {
    $this->assertSession()
      ->elementExists('css', '.views-exposed-form .form-actions input[type = "submit"]')
      ->press();

    $this->awaitAjax();
  }

}
