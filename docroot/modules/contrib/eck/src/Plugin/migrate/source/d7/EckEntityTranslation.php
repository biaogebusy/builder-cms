<?php

namespace Drupal\eck\Plugin\migrate\source\d7;

/**
 * Drupal 7 ECK entity translations source plugin.
 *
 * For additional configuration keys, refer to the parent classes:
 *
 * @see \Drupal\eck\Plugin\migrate\source\d7\EckEntity
 * @see \Drupal\migrate\Plugin\migrate\source\SqlBase
 * @see \Drupal\migrate\Plugin\migrate\source\SourcePluginBase
 *
 * @MigrateSource(
 *   id = "d7_eck_entity_translation",
 *   source_module = "entity_translation"
 * )
 */
class EckEntityTranslation extends EckEntity {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();

    $query->innerJoin('entity_translation', 'et', 'et.entity_id = eck.id');
    $query->fields('et');
    $query->condition('et.entity_type', $this->entityType);
    $query->condition('et.source', '', '<>');
    $query->orderBy('et.revision_id');
    $query->orderBy('et.language');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    return parent::fields() + [
      'entity_type' => $this->t('Entity translation entity type.'),
      'entity_id' => $this->t('Entity translation entity ID.'),
      'revision_id' => $this->t('Entity translation revision ID.'),
      'source' => $this->t('Entity translation source language code.'),
      'uid' => $this->t('The author of this translation.'),
      'status' => $this->t('Boolean indicating whether the translation is published (visible to non-administrators).'),
      'translate' => $this->t('A boolean indicating whether this translation needs to be updated.'),
      'created' => $this->t('The Unix timestamp when the translation was created.'),
      'changed' => $this->t('The Unix timestamp when the translation was most recently saved.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return parent::getIds() +
      [
        'language' => [
          'type' => 'string',
          'alias' => 'et',
        ],
      ];
  }

}
