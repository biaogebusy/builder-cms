<?php

declare(strict_types = 1);

namespace Drupal\entity_share_entity_test\Entity;

use Drupal\entity_test\Entity\EntityTest;

/**
 * Test entity class.
 *
 * @ContentEntityType(
 *   id = "entity_test_not_translatable",
 *   label = @Translation("Entity Test Not Translatable"),
 *   handlers = {
 *     "access" = "Drupal\entity_test\EntityTestAccessControlHandler",
 *   },
 *   base_table = "entity_test_not_translatable",
 *   translatable = FALSE,
 *   entity_keys = {
 *     "uuid" = "uuid",
 *     "id" = "id",
 *     "label" = "name",
 *     "bundle" = "type",
 *   }
 * )
 */
class EntityTestNotTranslatable extends EntityTest {

}
