<?php

namespace Drupal\chosen_test\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for chosen_test.
 */
class ChosenTestHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type) {
    if ($entity_type->id() !== 'node') {
      return [];
    }
    $fields['chosen_test_base_field'] = BaseFieldDefinition::create('list_string')
      ->setLabel($this->t('Chosen test base field'))
      ->setCardinality(3)
      ->setSetting('allowed_values', [
        'one' => 'One',
        'two' => 'Two',
        'three' => 'Three',
        'four' => 'Four',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 99,
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}
