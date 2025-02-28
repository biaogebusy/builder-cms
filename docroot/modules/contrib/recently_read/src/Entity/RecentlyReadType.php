<?php

namespace Drupal\recently_read\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the Recently read type entity.
 *
 * @ConfigEntityType(
 *   id = "recently_read_type",
 *   label = @Translation("Recently read type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\recently_read\RecentlyReadTypeListBuilder",
 *     "form" = {
 *       "edit" = "Drupal\recently_read\Form\RecentlyReadTypeForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "recently_read_type",
 *   admin_permission = "administer site configuration",
 *   bundle_of = "recently_read",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "types" = "types"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/system/recently-read/{recently_read_type}",
 *     "edit-form" = "/admin/config/system/recently-read/{recently_read_type}/edit",
 *     "collection" = "/admin/config/system/recently-read"
 *   }
 * )
 */
class RecentlyReadType extends ConfigEntityBundleBase implements RecentlyReadTypeInterface {

  /**
   * The Recently read type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Recently read type label.
   *
   * @var string
   */
  protected $label = '';

  /**
   * The Recently read types.
   *
   * @var array
   */
  protected $types = [];

  /**
   * {@inheritdoc}
   */
  public function getTypes() {
    return array_filter($this->get('types'));
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    $types = array_filter($this->get('types'));
    sort($types);

    $this->set('types', $types);
  }

}
