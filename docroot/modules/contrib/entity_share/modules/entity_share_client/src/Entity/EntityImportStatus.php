<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the entity_import_status entity class.
 *
 * @ContentEntityType(
 *   id = "entity_import_status",
 *   label = @Translation("Entity import status"),
 *   label_collection = @Translation("Entity import statuses"),
 *   base_table = "entity_import_status",
 *   entity_keys = {
 *     "id" = "id",
 *     "langcode" = "langcode",
 *   },
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\entity_share_client\EntityImportStatusListBuilder",
 *     "views_data" = "Drupal\entity_share_client\EntityImportStatusViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *   },
 *   admin_permission = "administer_import_status_entities",
 *   links = {
 *     "canonical" = "/admin/content/entity_share/import_status/{entity_import_status}",
 *     "delete-form" = "/admin/content/entity_share/import_status/{entity_import_status}/delete",
 *     "collection" = "/admin/content/entity_share/import_status",
 *   },
 * )
 */
class EntityImportStatus extends ContentEntityBase implements EntityImportStatusInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    $fields = parent::baseFieldDefinitions($entity_type);

    // The fields used to relate to the imported entity.
    $fields['entity_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('The identifier of imported entity on Client.'));

    $fields['entity_uuid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity UUID'))
      ->setDescription(t('The UUID of imported entity.'));

    $fields['entity_type_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type'))
      ->setDescription(t('The identifier of entity type of imported entity.'));

    $fields['entity_bundle'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity bundle'))
      ->setDescription(t('The bundle of imported entity.'));

    $fields['remote_website'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Remote website'))
      ->setDescription(t('The identifier of the remote website.'));

    $fields['channel_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Channel'))
      ->setDescription(t('The identifier of the import channel.'));

    // The fields containing the actual information about import.
    $fields['last_import'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Last import'))
      ->setDescription(t('The time of last import of imported entity.'));

    $fields['policy'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Policy'))
      ->setDescription(t('The import policy.'))
      ->setDefaultValue(EntityImportStatusInterface::IMPORT_POLICY_DEFAULT);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getLastImport() {
    return $this->get('last_import')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setLastImport($timestamp) {
    $this->set('last_import', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPolicy() {
    return $this->get('policy')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPolicy($policy) {
    $this->set('policy', $policy);
    return $this;
  }

}
