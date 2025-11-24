<?php

namespace Drupal\commerce_test\Plugin\Commerce\EntityTrait;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce\Attribute\CommerceEntityTrait;
use Drupal\commerce\Plugin\Commerce\EntityTrait\EntityTraitBase;
use Drupal\entity\BundleFieldDefinition;

/**
 * Provides the first entity trait.
 */
#[CommerceEntityTrait(
  id: "first",
  label: new TranslatableMarkup("First"),
  entity_types: ["commerce_store"],
)]
class First extends EntityTraitBase {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = [];
    $fields['phone'] = BundleFieldDefinition::create('telephone')
      ->setLabel(t('Phone'))
      ->setRequired(TRUE);

    return $fields;
  }

}
