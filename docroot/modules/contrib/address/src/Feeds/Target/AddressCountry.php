<?php

namespace Drupal\address\Feeds\Target;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;

/**
 * Defines an address country field mapper.
 *
 * @FeedsTarget(
 *   id = "address_country_feeds_target",
 *   field_types = {"address_country"}
 * )
 */
class AddressCountry extends FieldTargetBase {

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    $definition = FieldTargetDefinition::createFromFieldDefinition($field_definition);
    $definition->addProperty('value');
    return $definition;
  }

}
