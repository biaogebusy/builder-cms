<?php

namespace Drupal\votingapi\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\votingapi\VoteTypeInterface;

/**
 * Defines the Vote type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "vote_type",
 *   label = @Translation("Vote type"),
 *   label_collection = @Translation("Vote types"),
 *   label_singular = @Translation("vote type"),
 *   label_plural = @Translation("vote types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count vote type",
 *     plural = "@count vote types",
 *   ),
 *   handlers = {
 *     "access" = "Drupal\votingapi\VoteTypeAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\votingapi\VoteTypeForm",
 *       "edit" = "Drupal\votingapi\VoteTypeForm",
 *       "delete" = "Drupal\votingapi\Form\VoteTypeDeleteConfirm"
 *     },
 *     "list_builder" = "Drupal\votingapi\VoteTypeListBuilder",
 *   },
 *   admin_permission = "administer vote types",
 *   bundle_of = "vote",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "edit-form" = "/admin/structure/vote-types/{vote_type}",
 *     "delete-form" = "/admin/structure/vote-types/{vote_type}/delete",
 *     "collection" = "/admin/structure/vote-types",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "value_type",
 *     "description",
 *   }
 * )
 */
class VoteType extends ConfigEntityBundleBase implements VoteTypeInterface {

  /**
   * The machine name of this vote type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the vote type.
   *
   * @var string
   */
  protected $label;

  /**
   * The type of value for this vote (percentage, points, etc.).
   *
   * @var string
   */
  protected $value_type;

  /**
   * A brief description of this vote type.
   *
   * @var string
   */
  protected $description;

  /**
   * {@inheritdoc}
   */
  public function getValueType() {
    return $this->value_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    // Clear the vote type cache to reflect the removal.
    $storage->resetCache(array_keys($entities));
    // TODO: needed?
  }

}
