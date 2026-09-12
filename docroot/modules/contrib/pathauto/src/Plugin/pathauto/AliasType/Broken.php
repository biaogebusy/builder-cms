<?php

namespace Drupal\pathauto\Plugin\pathauto\AliasType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\pathauto\Attribute\AliasType;

/**
 * Defines a fallback plugin for missing AliasType plugins.
 */
#[AliasType(
  id: 'broken',
  label: new TranslatableMarkup('Broken'),
)]
class Broken extends EntityAliasTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->t('Broken type');
  }

}
