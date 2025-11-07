<?php

declare(strict_types = 1);

namespace Drupal\entity_share_entity_test\Entity;

/**
 * Test entity class.
 *
 * @ContentEntityType(
 *   id = "entity_test_not_translatable_el",
 *   label = @Translation("Entity Test Not Translatable Empty Langcode"),
 *   handlers = {
 *     "access" = "Drupal\entity_test\EntityTestAccessControlHandler",
 *   },
 *   base_table = "entity_test_not_translatable_el",
 *   entity_keys = {
 *     "uuid" = "uuid",
 *     "id" = "id",
 *     "label" = "name",
 *     "bundle" = "type",
 *     "langcode" = "",
 *   }
 * )
 */
class EntityTestNotTranslatableEL extends EntityTestNotTranslatable {

}
