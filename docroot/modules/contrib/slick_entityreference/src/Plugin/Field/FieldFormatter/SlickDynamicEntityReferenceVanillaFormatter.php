<?php

namespace Drupal\slick_entityreference\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\slick\Plugin\Field\FieldFormatter\SlickEntityFormatterBase;
use Drupal\dynamic_entity_reference\Plugin\Field\FieldFormatter\DynamicEntityReferenceFormatterTrait;

/**
 * Plugin implementation of the 'Slick Dynamic Entity Reference' formatter.
 *
 * @FieldFormatter(
 *   id = "slick_dynamicentityreference_vanilla",
 *   label = @Translation("Slick Dynamic Entity Reference Vanilla"),
 *   description = @Translation("Display the vanilla dynamic entity reference as a Slick carousel."),
 *   field_types = {
 *     "dynamic_entity_reference"
 *   },
 *   quickedit = {
 *     "editor" = "disabled"
 *   }
 * )
 */
class SlickDynamicEntityReferenceVanillaFormatter extends SlickEntityFormatterBase {
  use DynamicEntityReferenceFormatterTrait;

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $storage = $field_definition->getFieldStorageDefinition();

    return $storage->isMultiple();
  }

  /**
   * {@inheritdoc}
   */
  public function prepareView(array $entities_items) {
    // Entity revision loading currently has no static/persistent cache and no
    // multiload. As entity reference checks _loaded, while we don't want to
    // indicate a loaded entity, when there is none, as it could cause errors,
    // we actually load the entity and set the flag.
    foreach ($entities_items as $items) {
      foreach ($items as $item) {

        if ($item->entity) {
          $item->_loaded = TRUE;
        }
      }
    }
  }

}
