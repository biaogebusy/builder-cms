<?php

namespace Drupal\slick_entityreference\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\slick\Plugin\Field\FieldFormatter\SlickEntityFormatterBase;

/**
 * Plugin implementation of the 'Slick Entity Reference' formatter.
 *
 * @FieldFormatter(
 *   id = "slick_entityreference_vanilla",
 *   label = @Translation("Slick Entity Reference Vanilla"),
 *   description = @Translation("Display the vanilla entity reference/entity reference revision as a Slick carousel."),
 *   field_types = {
 *     "entity_reference",
 *     "entity_reference_revisions"
 *   },
 *   quickedit = {
 *     "editor" = "disabled"
 *   }
 * )
 */
class SlickEntityReferenceVanillaFormatter extends SlickEntityFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $storage = $field_definition->getFieldStorageDefinition();

    return $storage->isMultiple();
  }

}
