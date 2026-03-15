<?php

namespace Drupal\social_auth\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\social_api\Entity\SocialApi;

/**
 * Defines the Social Auth entity.
 *
 * @ingroup social_auth
 *
 * @ContentEntityType(
 *   id = "social_auth",
 *   label = @Translation("Social Auth"),
 *   label_collection = @Translation("Social Auth profiles"),
 *   handlers = {
 *     "access" = "Drupal\social_auth\Entity\SocialAuthAccessControlHandler",
 *     "list_builder" = "Drupal\social_auth\SocialAuthListBuilder",
 *     "views_data" = "Drupal\social_auth\SocialAuthViewsData",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *   },
 *   base_table = "social_auth",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "user_id" = "user_id",
 *     "plugin_id" = "plugin_id",
 *     "provider_user_id" = "provider_user_id"
 *   },
 *   admin_permission = "administer social auth profiles",
 *   links = {
 *     "collection" = "/admin/social-auth/profiles",
 *     "delete-form" = "/admin/social-auth/profiles/{social_auth}/delete",
 *   },
 * )
 */
class SocialAuth extends SocialApi implements ContentEntityInterface {

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    $additional_data = $values['additional_data'] ?? NULL;
    if ($additional_data) {
      $values['additional_data'] = static::encode($additional_data);
    }

    return parent::preCreate($storage, $values);
  }

  /**
   * Sets the additional data.
   *
   * @param array $data
   *   The additional data.
   *
   * @return \Drupal\social_auth\Entity\SocialAuth
   *   Drupal Social Auth Entity.
   */
  public function setAdditionalData(array $data): static {
    $this->set('additional_data', $this->encode($data));
    return $this;
  }

  /**
   * Returns the serialized additional data.
   *
   * @return array
   *   The additional data.
   */
  public function getAdditionalData(): array {
    return $this->hasField('additional_data') && !$this->get('additional_data')->isEmpty()
      ? $this->decode($this->get('additional_data')->value)
      : [];
  }

  /**
   * Updates the created time field.
   *
   * @param int $timestamp
   *   The social auth creation timestamp.
   *
   * @return \Drupal\social_auth\Entity\SocialAuth
   *   Drupal Social Auth Entity.
   */
  public function setCreatedTime(int $timestamp): static {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * Gets the user creation time.
   *
   * @return int
   *   Creation timestamp Social Auth entity.
   */
  public function getCreatedTime(): int {
    return $this->get('created')->value;
  }

  /**
   * Updates the user data changed time field.
   *
   * @param int $timestamp
   *   The social auth changed timestamp.
   *
   * @return \Drupal\social_auth\Entity\SocialAuth
   *   Drupal Social Auth Entity.
   */
  public function setChangedTime(int $timestamp): static {
    $this->set('changed', $timestamp);
    return $this;
  }

  /**
   * Gets the changed time field.
   *
   * @return int
   *   Changed timestamp Social Auth entity.
   */
  public function getChangedTime(): int {
    return $this->get('changed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Social Auth record.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The Social Auth user UUID.'))
      ->setReadOnly(TRUE);

    // The ID of user account associated.
    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User ID'))
      ->setDescription(t('The Drupal uid associated with social network.'))
      ->setSetting('target_type', 'user');

    // Name of the social network account associated.
    $fields['plugin_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Plugin ID'))
      ->setDescription(t('Identifier for Social Auth implementer.'));

    // Unique Account ID returned by the social network provider.
    $fields['provider_user_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Provider user ID'))
      ->setDescription(t('The unique user ID in the provider.'));

    // Token received after user authentication.
    $fields['token'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Token received after user authentication'))
      ->setDescription(t('Used to make API calls.'));

    // Additional Data collected social network provider.
    $fields['additional_data'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Additional data'))
      ->setDescription(t('The additional data kept for future use.'));

    // User creation time.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    // User modified time.
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

  /**
   * Encodes array to store in the additional data field.
   *
   * @param array $data
   *   The additional data.
   *
   * @return string
   *   The serialized data.
   */
  protected static function encode(array $data): string {
    return json_encode($data);
  }

  /**
   * Decodes string stored in the additional data field.
   *
   * @param string $data
   *   The encoded additional data.
   *
   * @return array
   *   The decoded data.
   */
  protected function decode(string $data): array {
    return json_decode($data, TRUE);
  }

}
