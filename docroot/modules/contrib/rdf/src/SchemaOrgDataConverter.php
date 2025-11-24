<?php

namespace Drupal\rdf;

/**
 * Provides a converter for schema.org data.
 */
class SchemaOrgDataConverter {

  /**
   * Converts an interaction count to a string with the interaction type.
   *
   * Schema.org defines a number of different interaction types.
   *
   * @param int $count
   *   The interaction count.
   * @param array $arguments
   *   An array of arguments defined in the mapping.
   *   Expected keys are:
   *     - interaction_type: The string to use for the type of interaction
   *       (e.g. UserComments).
   *
   * @return string
   *   The formatted string.
   *
   * @see http://schema.org/UserInteraction
   */
  public static function interactionCount(int $count, array $arguments): string {
    $interaction_type = $arguments['interaction_type'];
    return "$interaction_type:$count";
  }

}
