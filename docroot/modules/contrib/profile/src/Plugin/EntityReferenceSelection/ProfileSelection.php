<?php

namespace Drupal\profile\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;

/**
 * Provides specific access control for the profile entity type.
 *
 * @deprecated in profile:8.x-1.0 and is removed from profile:2.0.0. Use the
 *  default selection instead.
 * @see https://www.drupal.org/node/3068777
 *
 * @EntityReferenceSelection(
 *   id = "default:profile",
 *   label = @Translation("Profile selection"),
 *   entity_types = {"profile"},
 *   group = "default",
 *   weight = 1
 * )
 */
class ProfileSelection extends DefaultSelection {}
