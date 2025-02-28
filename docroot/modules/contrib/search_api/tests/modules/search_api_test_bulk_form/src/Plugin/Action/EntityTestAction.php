<?php

namespace Drupal\search_api_test_bulk_form\Plugin\Action;

use Drupal\Core\Action\ActionBase;

/**
 * Provides an action for the entity_test entity type.
 *
 * @Action(
 *   id = "search_api_test_bulk_form_entity_test",
 *   label = @Translation("Search API test bulk form action: entity_test"),
 *   type = "entity_test",
 * )
 */
class EntityTestAction extends ActionBase {

  use TestActionTrait;

}
