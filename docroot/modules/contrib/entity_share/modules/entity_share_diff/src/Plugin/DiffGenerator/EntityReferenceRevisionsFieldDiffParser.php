<?php

declare(strict_types = 1);

namespace Drupal\entity_share_diff\Plugin\DiffGenerator;

/**
 * Plugin to diff entity reference fields.
 *
 * @DiffGenerator(
 *   id = "entity_reference_revisions_field_diff_parser",
 *   label = @Translation("Entity Reference Revisions Field Parser"),
 *   field_types = {
 *     "entity_reference_revisions"
 *   },
 * )
 */
class EntityReferenceRevisionsFieldDiffParser extends EntityReferenceFieldDiffParser {
}
