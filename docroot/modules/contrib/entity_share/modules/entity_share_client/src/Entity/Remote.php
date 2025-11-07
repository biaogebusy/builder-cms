<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationInterface;

/**
 * Defines the Remote entity.
 *
 * @ConfigEntityType(
 *   id = "remote",
 *   label = @Translation("Remote"),
 *   handlers = {
 *     "list_builder" = "Drupal\entity_share_client\RemoteListBuilder",
 *     "form" = {
 *       "add" = "Drupal\entity_share_client\Form\RemoteForm",
 *       "edit" = "Drupal\entity_share_client\Form\RemoteForm",
 *       "delete" = "Drupal\entity_share_client\Form\RemoteDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "remote",
 *   admin_permission = "administer_remote_entity",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "url",
 *     "auth",
 *   },
 *   links = {
 *     "canonical" = "/admin/config/services/entity_share/remote/{remote}",
 *     "add-form" = "/admin/config/services/entity_share/remote/add",
 *     "edit-form" = "/admin/config/services/entity_share/remote/{remote}/edit",
 *     "delete-form" = "/admin/config/services/entity_share/remote/{remote}/delete",
 *     "collection" = "/admin/config/services/entity_share/remote"
 *   }
 * )
 */
class Remote extends ConfigEntityBase implements RemoteInterface {

  /**
   * The Remote ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Remote label.
   *
   * @var string
   */
  protected $label;

  /**
   * The Remote URL.
   *
   * @var string
   */
  protected $url;

  /**
   * An associative array of the authorization plugin data.
   *
   * @var array
   */
  protected $auth;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Ensure no trailing slash at the end of the remote URL.
    $remote_url = $this->get('url');
    $matches = [];
    if (!empty($remote_url) && preg_match('/(.*)\/$/', $remote_url, $matches)) {
      $this->set('url', $matches[1]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthPlugin() {
    $pluginData = $this->auth;
    if (!empty($pluginData['pid'])) {
      // DI not available in entities:
      // https://www.drupal.org/project/drupal/issues/2142515.
      /** @var \Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationPluginManager $manager */
      $manager = \Drupal::service('plugin.manager.entity_share_client_authorization');
      $pluginId = $pluginData['pid'];
      unset($pluginData['pid']);
      return $manager->createInstance($pluginId, $pluginData);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function mergePluginConfig(ClientAuthorizationInterface $plugin) {
    $auth = ['pid' => $plugin->getPluginId()] +
      $plugin->getConfiguration();
    $this->auth = $auth;
  }

  /**
   * {@inheritdoc}
   */
  public function getHttpClient(bool $json) {
    $plugin = $this->getAuthPlugin();
    if ($json) {
      return $plugin->getJsonApiClient($this->url);
    }
    return $plugin->getClient($this->url);
  }

}
