<?php

namespace Drupal\entity_module_test\Entity;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\entity\Revision\RevisionableContentEntityBase;

/**
 * Provides a test entity which uses all the capabilities of entity module.
 *
 * @ContentEntityType(
 *   id = "entity_test_enhanced",
 *   label = @Translation("Enhanced entity"),
 *   label_collection = @Translation("Enhanced entities"),
 *   label_singular = @Translation("enhanced entity"),
 *   label_plural = @Translation("enhanced entities"),
 *   label_count = @PluralTranslation(
 *     singular = "@count enhanced entity",
 *     plural = "@count enhanced entities",
 *   ),
 *   handlers = {
 *     "storage" = "\Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "access" = "\Drupal\entity\EntityAccessControlHandler",
 *     "query_access" = "\Drupal\entity\QueryAccess\QueryAccessHandler",
 *     "permission_provider" = "\Drupal\entity\EntityPermissionProvider",
 *     "form" = {
 *       "add" = "\Drupal\entity_module_test\Form\EnhancedEntityForm",
 *       "edit" = "\Drupal\entity_module_test\Form\EnhancedEntityForm",
 *       "duplicate" = "\Drupal\entity_module_test\Form\EnhancedEntityForm",
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
 *     "local_task_provider" = {
 *       "default" = "\Drupal\entity\Menu\DefaultEntityLocalTaskProvider",
 *     },
 *     "list_builder" = "\Drupal\Core\Entity\EntityListBuilder",
 *     "views_data" = "\Drupal\entity\EntityViewsData",
 *   },
 *   base_table = "entity_test_enhanced",
 *   data_table = "entity_test_enhanced_field_data",
 *   revision_table = "entity_test_enhanced_revision",
 *   revision_data_table = "entity_test_enhanced_field_revision",
 *   translatable = TRUE,
 *   revisionable = TRUE,
 *   admin_permission = "administer entity_test_enhanced",
 *   permission_granularity = "bundle",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "type",
 *     "revision" = "vid",
 *     "langcode" = "langcode",
 *     "label" = "name",
 *     "published" = "status",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log_message"
 *   },
 *   links = {
 *     "add-page" = "/entity_test_enhanced/add",
 *     "add-form" = "/entity_test_enhanced/add/{type}",
 *     "edit-form" = "/entity_test_enhanced/{entity_test_enhanced}/edit",
 *     "duplicate-form" = "/entity_test_enhanced/{entity_test_enhanced}/duplicate",
 *     "canonical" = "/entity_test_enhanced/{entity_test_enhanced}",
 *     "collection" = "/entity_test_enhanced",
 *     "delete-form" = "/entity_test_enhanced/{entity_test_enhanced}/delete",
 *     "delete-multiple-form" = "/entity_test_enhanced/delete",
 *     "revision" = "/entity_test_enhanced/{entity_test_enhanced}/revisions/{entity_test_enhanced_revision}/view",
 *     "revision-revert-form" = "/entity_test_enhanced/{entity_test_enhanced}/revisions/{entity_test_enhanced_revision}/revert",
 *     "version-history" = "/entity_test_enhanced/{entity_test_enhanced}/revisions",
 *   },
 * )
 */
class EnhancedEntity extends RevisionableContentEntityBase implements EntityPublishedInterface {

  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel('Name')
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ]);

    return $fields;
  }

}
