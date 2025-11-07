<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Channel entity.
 *
 * @ConfigEntityType(
 *   id = "channel",
 *   label = @Translation("Channel"),
 *   handlers = {
 *     "list_builder" = "Drupal\entity_share_server\ChannelListBuilder",
 *     "form" = {
 *       "add" = "Drupal\entity_share_server\Form\ChannelForm",
 *       "edit" = "Drupal\entity_share_server\Form\ChannelForm",
 *       "delete" = "Drupal\entity_share_server\Form\ChannelDeleteForm",
 *       "filter_add" = "Drupal\entity_share_server\Form\FilterAddForm",
 *       "filter_edit" = "Drupal\entity_share_server\Form\FilterEditForm",
 *       "filter_delete" = "Drupal\entity_share_server\Form\FilterDeleteForm",
 *       "sort_add" = "Drupal\entity_share_server\Form\SortAddForm",
 *       "sort_edit" = "Drupal\entity_share_server\Form\SortEditForm",
 *       "sort_delete" = "Drupal\entity_share_server\Form\SortDeleteForm",
 *       "search_add" = "Drupal\entity_share_server\Form\SearchAddForm",
 *       "search_edit" = "Drupal\entity_share_server\Form\SearchEditForm",
 *       "search_delete" = "Drupal\entity_share_server\Form\SearchDeleteForm",
 *       "group_add" = "Drupal\entity_share_server\Form\GroupAddForm",
 *       "group_edit" = "Drupal\entity_share_server\Form\GroupEditForm",
 *       "group_delete" = "Drupal\entity_share_server\Form\GroupDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "channel",
 *   admin_permission = "administer_channel_entity",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "channel_entity_type",
 *     "channel_bundle",
 *     "channel_langcode",
 *     "channel_filters",
 *     "channel_groups",
 *     "channel_sorts",
 *     "channel_searches",
 *     "channel_maxsize",
 *     "access_by_permission",
 *     "authorized_roles",
 *     "authorized_users",
 *   },
 *   links = {
 *     "canonical" = "/admin/config/services/entity_share/channel/{channel}",
 *     "add-form" = "/admin/config/services/entity_share/channel/add",
 *     "edit-form" = "/admin/config/services/entity_share/channel/{channel}/edit",
 *     "delete-form" = "/admin/config/services/entity_share/channel/{channel}/delete",
 *     "collection" = "/admin/config/services/entity_share/channel",
 *     "filter-add" = "/admin/config/services/entity_share/channel/{channel}/filters/add",
 *     "filter-edit" = "/admin/config/services/entity_share/channel/{channel}/filters/{filter}/edit",
 *     "filter-delete" = "/admin/config/services/entity_share/channel/{channel}/filters/{filter}/delete",
 *     "sort-add" = "/admin/config/services/entity_share/channel/{channel}/sorts/add",
 *     "sort-edit" = "/admin/config/services/entity_share/channel/{channel}/sorts/{sort}/edit",
 *     "sort-delete" = "/admin/config/services/entity_share/channel/{channel}/sorts/{sort}/delete",
 *     "search-add" = "/admin/config/services/entity_share/channel/{channel}/searches/add",
 *     "search-edit" = "/admin/config/services/entity_share/channel/{channel}/searches/{search}/edit",
 *     "search-delete" = "/admin/config/services/entity_share/channel/{channel}/searches/{search}/delete",
 *     "group-add" = "/admin/config/services/entity_share/channel/{channel}/groups/add",
 *     "group-edit" = "/admin/config/services/entity_share/channel/{channel}/groups/{group}/edit",
 *     "group-delete" = "/admin/config/services/entity_share/channel/{channel}/groups/{group}/delete",
 *   }
 * )
 *
 * @SuppressWarnings(PHPMD.CamelCasePropertyName)
 */
class Channel extends ConfigEntityBase implements ChannelInterface {

  /**
   * The channel ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The channel label.
   *
   * @var string
   */
  protected $label;

  /**
   * The channel entity type.
   *
   * @var string
   */
  protected $channel_entity_type;

  /**
   * The channel bundle.
   *
   * @var string
   */
  protected $channel_bundle;

  /**
   * The channel langcode.
   *
   * @var string
   */
  protected $channel_langcode;

  /**
   * The channel filters.
   *
   * @var array
   */
  protected $channel_filters;

  /**
   * The channel groups.
   *
   * @var array
   */
  protected $channel_groups;

  /**
   * The channel sorts.
   *
   * @var array
   */
  protected $channel_sorts;

  /**
   * The channel searches configuration. Used on pull form to search text.
   *
   * @var array
   */
  protected $channel_searches;

  /**
   * The channel max size.
   *
   * @var int
   */
  protected $channel_maxsize = 50;

  /**
   * Authorized all the users with the permission 'Access channels list'.
   *
   * @var bool
   */
  protected $access_by_permission;

  /**
   * The user roles authorized to see this channel.
   *
   * @var string[]
   */
  protected $authorized_roles;

  /**
   * The UUIDs of the users authorized to see this channel.
   *
   * @var string[]
   */
  protected $authorized_users;

  /**
   * {@inheritdoc}
   */
  public function removeAuthorizedRole($role) {
    $authorized_roles = $this->authorized_roles;
    $key = array_search($role, $authorized_roles);
    if ($key !== FALSE) {
      unset($authorized_roles[$key]);
      $this->set('authorized_roles', $authorized_roles);
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function removeAuthorizedUser($uuid) {
    $authorized_users = $this->authorized_users;
    $key = array_search($uuid, $authorized_users);
    if ($key !== FALSE) {
      unset($authorized_users[$key]);
      $this->set('authorized_users', $authorized_users);
      return TRUE;
    }

    return FALSE;
  }

}
