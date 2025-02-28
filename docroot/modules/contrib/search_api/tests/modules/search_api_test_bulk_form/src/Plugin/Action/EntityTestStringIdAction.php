<?php

namespace Drupal\search_api_test_bulk_form\Plugin\Action;

use Drupal\Core\Action\ActionBase;

/**
 * Provides an action for the entity_test_string_id entity type.
 *
 * @Action(
 *   id = "search_api_test_bulk_form_entity_test_string_id",
 *   label = @Translation("Search API test bulk form action: entity_test_string_id"),
 *   type = "entity_test_string_id",
 * )
 */
class EntityTestStringIdAction extends ActionBase {

  use TestActionTrait;

}
