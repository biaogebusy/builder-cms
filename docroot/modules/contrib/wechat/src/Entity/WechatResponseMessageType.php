<?php

/**
 * @file
 * Contains \Drupal\wechat\Entity\WechatResponseMessageType.
 */

namespace Drupal\wechat\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\wechat\WechatResponseMessageTypeInterface;

/**
 * Defines the Wechat response message type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "wechat_response_message_type",
 *   label = @Translation("Response message type"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\wechat\WechatResponseMessageTypeForm",
 *       "edit" = "Drupal\wechat\WechatResponseMessageTypeForm",
 *       "delete" = "Drupal\wechat\Form\WechatResponseMessageTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     }, 
 *     "list_builder" = "Drupal\wechat\WechatResponseMessageTypeListBuilder",
 *   },
 *   admin_permission = "access administration pages",
 *   config_prefix = "response_message_type",
 *   bundle_of = "wechat_response_message",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "add-form" = "/admin/wechat/config/response-message-types/add", 
 *     "edit-form" = "/admin/wechat/config/response-message-types/manage/{wechat_response_message_type}",
 *     "delete-form" = "/admin/wechat/config/response-message-types/manage/{wechat_response_message_type}/delete",
 *     "collection" = "/admin/wechat/config/response-message-types",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *   }
 * )
 */
class WechatResponseMessageType extends ConfigEntityBundleBase implements WechatResponseMessageTypeInterface {

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
