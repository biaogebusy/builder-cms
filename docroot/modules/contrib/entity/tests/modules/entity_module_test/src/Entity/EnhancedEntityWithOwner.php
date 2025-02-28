<?php

namespace Drupal\entity_module_test\Entity;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\entity\Revision\RevisionableContentEntityBase;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Provides a test entity which uses all the capabilities of entity module.
 *
 * @ContentEntityType(
 *   id = "entity_test_enhanced_with_owner",
 *   label = @Translation("Enhanced entity with owner"),
 *   label_collection = @Translation("Enhanced entities with owner"),
 *   label_singular = @Translation("enhanced entity with owner"),
 *   label_plural = @Translation("enhanced entities with owner"),
 *   label_count = @PluralTranslation(
 *     singular = "@count enhanced entity with owner",
 *     plural = "@count enhanced entities with owner",
 *   ),
 *   handlers = {
 *     "storage" = "\Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "access" = "\Drupal\entity\UncacheableEntityAccessControlHandler",
 *     "query_access" = "\Drupal\entity\QueryAccess\UncacheableQueryAccessHandler",
 *     "permission_provider" = "\Drupal\entity\UncacheableEntityPermissionProvider",
 *     "form" = {
 *       "add" = "\Drupal\Core\Entity\ContentEntityForm",
 *       "edit" = "\Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "\Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "\Drupal\entity\Routing\DefaultHtmlRouteProvider",
 *       "revision" = "\Drupal\entity\Routing\RevisionRouteProvider",
 *       "delete-multiple" = "\Drupal\entity\Routing\DeleteMultipleRouteProvider",
 *     },
 *     "local_action_provider" = {
 *       "collection" = "\Drupal\entity\Menu\EntityCollectionLocalActionProvider",
 *     },
 *     "list_builder" = "\Drupal\entity\BulkFormEntityListBuilder",
 *     "views_data" = "\Drupal\entity\EntityViewsData",
 *   },
 *   base_table = "entity_test_enhanced_with_owner",
 *   data_table = "entity_test_enhanced_with_owner_field_data",
 *   revision_table = "entity_test_enhanced_with_owner_revision",
 *   revision_data_table = "entity_test_enhanced_with_owner_field_revision",
 *   translatable = TRUE,
 *   revisionable = TRUE,
 *   admin_permission = "administer entity_test_enhanced_with_owner",
 *   permission_granularity = "bundle",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "type",
 *     "revision" = "vid",
 *     "langcode" = "langcode",
 *     "label" = "name",
 *     "owner" = "user_id",
 *     "published" = "status",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log_message"
 *   },
 *   links = {
 *     "add-page" = "/entity_test_enhanced_with_owner/add",
 *     "add-form" = "/entity_test_enhanced_with_owner/add/{type}",
 *     "edit-form" = "/entity_test_enhanced_with_owner/{entity_test_enhanced_with_owner}/edit",
 *     "duplicate-form" = "/entity_test_enhanced/{entity_test_enhanced}/duplicate",
 *     "canonical" = "/entity_test_enhanced_with_owner/{entity_test_enhanced_with_owner}",
 *     "collection" = "/entity_test_enhanced_with_owner",
 *     "delete-multiple-form" = "/entity_test_enhanced_with_owner/delete",
 *   },
 * )
 */
class EnhancedEntityWithOwner extends RevisionableContentEntityBase implements EntityOwnerInterface, EntityPublishedInterface {

  use EntityOwnerTrait, EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel('Name')
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ]);

    $fields['user_id']
      // Default EntityTest entities to have the root user as the owner, to
      // simplify testing.
      ->setDefaultValue([0 => ['target_id' => 1]])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -1,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
      ]);

    return $fields;
  }

}
