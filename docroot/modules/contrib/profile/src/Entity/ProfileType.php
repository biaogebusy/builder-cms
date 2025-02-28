<?php

namespace Drupal\profile\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the profile type entity class.
 *
 * @ConfigEntityType(
 *   id = "profile_type",
 *   label = @Translation("Profile type"),
 *   label_collection = @Translation("Profile types"),
 *   label_singular = @Translation("profile type"),
 *   label_plural = @Translation("profile types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count profile type",
 *     plural = "@count profile types",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\profile\ProfileTypeListBuilder",
 *     "form" = {
 *       "default" = "Drupal\profile\Form\ProfileTypeForm",
 *       "add" = "Drupal\profile\Form\ProfileTypeForm",
 *       "edit" = "Drupal\profile\Form\ProfileTypeForm",
 *       "duplicate" = "Drupal\profile\Form\ProfileTypeForm",
 *       "delete" = "Drupal\profile\Form\ProfileTypeDeleteForm"
 *     },
 *     "local_task_provider" = {
 *       "default" = "Drupal\entity\Menu\DefaultEntityLocalTaskProvider",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer profile types",
 *   config_prefix = "type",
 *   bundle_of = "profile",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "display_label",
 *     "multiple",
 *     "registration",
 *     "roles",
 *     "allow_revisions",
 *     "new_revision",
 *   },
 *   links = {
 *     "add-form" = "/admin/config/people/profile-types/add",
 *     "edit-form" = "/admin/config/people/profile-types/manage/{profile_type}",
 *     "duplicate-form" = "/admin/config/people/profile-types/manage/{profile_type}/duplicate",
 *     "delete-form" = "/admin/config/people/profile-types/manage/{profile_type}/delete",
 *     "collection" = "/admin/config/people/profile-types"
 *   }
 * )
 */
class ProfileType extends ConfigEntityBundleBase implements ProfileTypeInterface {

  /**
   * The profile type ID.
   *
   * @var int
   */
  protected $id;

  /**
   * The profile type label.
   *
   * @var string
   */
  protected $label;

  /**
   * The profile type display label.
   *
   * @var string
   */
  protected $display_label;

  /**
   * Whether a user can have multiple profiles of this type.
   *
   * @var bool
   */
  protected $multiple = FALSE;

  /**
   * Whether a profile of this type should be created during registration.
   *
   * @var bool
   */
  protected $registration = FALSE;

  /**
   * The user roles allowed to have profiles of this type.
   *
   * @var array
   */
  protected $roles = [];

  /**
   * Whether profiles of this type allow revisions.
   *
   * @var bool
   */
  protected $allow_revisions = FALSE;

  /**
   * Should profiles of this type always generate revisions.
   *
   * @var bool
   */
  protected $new_revision = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getDisplayLabel() {
    return $this->display_label;
  }

  /**
   * {@inheritdoc}
   */
  public function setDisplayLabel($display_label) {
    $this->display_label = $display_label;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function allowsMultiple() {
    return $this->multiple;
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple($multiple) {
    $this->multiple = $multiple;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRegistration() {
    return $this->registration;
  }

  /**
   * {@inheritdoc}
   */
  public function setRegistration($registration) {
    $this->registration = $registration;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRoles() {
    return $this->roles;
  }

  /**
   * {@inheritdoc}
   */
  public function setRoles(array $roles) {
    $this->roles = $roles;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function allowsRevisions() {
    return $this->allow_revisions;
  }

  /**
   * {@inheritdoc}
   */
  public function shouldCreateNewRevision() {
    return $this->allowsRevisions() && $this->new_revision;
  }

  /**
   * {@inheritdoc}
   */
  public function showRevisionUi() {
    return $this->allowsRevisions() && $this->entityTypeManager()->getDefinition('profile')->showRevisionUi();
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    if ($this->shouldCreateNewRevision() && !$this->allowsRevisions()) {
      $this->set('new_revision', FALSE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Rebuild the user page tab.
    \Drupal::service('router.builder')->setRebuildNeeded();
    // Update the "register" form display, if needed.
    $original_registration = isset($this->original) ? $this->original->getRegistration() : FALSE;
    $registration = $this->getRegistration();
    if ($original_registration != $registration) {
      $register_display = EntityFormDisplay::load('user.user.register');
      if (!$register_display) {
        // The "register" form mode isn't customized by default.
        $default_display = EntityFormDisplay::load('user.user.default');
        if (!$default_display) {
          // @todo Remove once we require Drupal 8.8. See #2835616.
          $default_display = EntityFormDisplay::create([
            'targetEntityType' => 'user',
            'bundle' => 'user',
            'mode' => 'default',
            'status' => TRUE,
          ]);
        }
        $register_display = $default_display->createCopy('register');
      }

      if ($registration) {
        $register_display->setComponent($this->id() . '_profiles', [
          'type' => 'profile_form',
          'weight' => 90,
        ]);
      }
      else {
        $register_display->removeComponent($this->id() . '_profiles');
      }
      $register_display->setStatus(TRUE);
      $register_display->save();
    }
  }

}
