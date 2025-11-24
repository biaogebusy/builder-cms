<?php

declare(strict_types=1);

namespace Drupal\private_message\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;

/**
 * The Private Message entity definition.
 *
 * @ContentEntityType(
 *   id = "private_message",
 *   label = @Translation("Private Message"),
 *   handlers = {
 *     "view_builder" = "Drupal\private_message\Entity\Builder\PrivateMessageViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\private_message\Form\PrivateMessageForm",
 *       "delete" = "Drupal\private_message\Form\PrivateMessageDeleteForm",
 *     },
 *     "access" = "Drupal\private_message\Entity\Access\PrivateMessageAccessControlHandler",
 *   },
 *   base_table = "private_messages",
 *   admin_permission = "administer private messages",
 *   fieldable = TRUE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "owner",
 *   },
 *   links = {
 *     "canonical" = "/private-message/{private_message}",
 *     "delete-form" = "/private-message/{private_message}/delete",
 *   },
 *   field_ui_base_route = "private_message.private_message_settings",
 * )
 */
class PrivateMessage extends ContentEntityBase implements PrivateMessageInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return (int) $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage() {
    @trigger_error(__METHOD__ . "() is deprecated in private_message:4.0.0 and is removed from private_message:5.0.0. Instead, access the 'message' field. See https://www.drupal.org/node/3490530", E_USER_DEPRECATED);
    return $this->get('message')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['id']->setLabel(t('Private message ID'))
      ->setDescription(t('The private message ID.'));

    $fields['uuid']->setDescription(t('The custom private message UUID.'));

    // No form field is provided, as the user will always be the current user.
    $fields['owner']
      ->setLabel(t('From'))
      ->setDescription(t('The author of the private message'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Body of the private message.
    $fields['message'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Message'))
      ->setRequired(TRUE)
      ->setCardinality(1)
      ->setTranslatable(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'label' => 'hidden',
        'settings' => [
          'placeholder' => 'Message',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'text_textfield',
        'settings' => [
          'trim_length' => '200',
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the private message was created.'))
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'weight' => -1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
