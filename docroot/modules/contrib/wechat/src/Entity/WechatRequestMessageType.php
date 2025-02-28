<?php

/**
 * @file
 * Contains \Drupal\wechat\Entity\WechatRequestMessageType.
 */

namespace Drupal\wechat\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\wechat\WechatRequestMessageTypeInterface;

/**
 * Defines the Wechat request message type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "wechat_request_message_type",
 *   label = @Translation("Request message type"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\wechat\WechatRequestMessageTypeForm",
 *       "edit" = "Drupal\wechat\WechatRequestMessageTypeForm",
 *       "delete" = "Drupal\wechat\Form\WechatRequestMessageTypeDeleteForm"
 *     },
 *     "list_builder" = "Drupal\wechat\WechatRequestMessageTypeListBuilder",
 *   },
 *   admin_permission = "access administration pages",
 *   config_prefix = "request_message_type",
 *   bundle_of = "wechat_request_message",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "edit-form" = "/admin/wechat/config/request-message-types/manage/{wechat_request_message_type}",
 *     "delete-form" = "/admin/wechat/config/request-message-types/manage/{wechat_request_message_type}/delete",
 *     "collection" = "/admin/wechat/config/request-message-types",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *   }
 * )
 */
class WechatRequestMessageType extends ConfigEntityBundleBase implements WechatRequestMessageTypeInterface {

  /**
   * The machine name of this request message type.
   *
   * @var string
   *
   */
  protected $id;

  /**
   * The human-readable name of the request message type.
   *
   * @var string
   *
   */
  protected $label;

  /**
   * A brief description of this request message type.
   *
   * @var string
   */
  protected $description;


  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

}
