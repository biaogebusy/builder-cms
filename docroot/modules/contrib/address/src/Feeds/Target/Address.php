<?php

namespace Drupal\address\Feeds\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;

/**
 * Defines an address field mapper.
 *
 * @FeedsTarget(
 *   id = "address_feeds_target",
 *   field_types = {"address"}
 * )
 */
class Address extends FieldTargetBase {

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    $definition = FieldTargetDefinition::createFromFieldDefinition($field_definition);
    $definition
      ->addProperty('langcode')
      ->addProperty('country_code')
      ->addProperty('administrative_area')
      ->addProperty('locality')
      ->addProperty('dependent_locality')
      ->addProperty('postal_code')
      ->addProperty('sorting_code')
      ->addProperty('address_line1')
      ->addProperty('address_line2')
      ->addProperty('organization')
      ->addProperty('given_name')
      ->addProperty('additional_name')
      ->addProperty('family_name');
    return $definition;
  }

}
