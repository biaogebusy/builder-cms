<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Mocks;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\entity_test\Entity\EntityTestMulRevChangedWithRevisionLog;

/**
 * Workaround for core bug.
 *
 * @see https://www.drupal.org/project/drupal/issues/2908210
 */
class EntityTestMulRevChangedWithRevisionLogFix extends EntityTestMulRevChangedWithRevisionLog {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Restore the normal field type so we don't sleep() needlessly.
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE);

    return $fields;
  }

}
