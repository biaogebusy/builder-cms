<?php

namespace Drupal\user_default_page\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\user_default_page\UserDefaultPageConfigEntityInterface;

/**
 * Defines the User default page entity.
 *
 * @ConfigEntityType(
 *   id = "user_default_page_config_entity",
 *   label = @Translation("User default page"),
 *   handlers = {
 *     "list_builder" = "Drupal\user_default_page\UserDefaultPageConfigEntityListBuilder",
 *     "form" = {
 *       "add" = "Drupal\user_default_page\Form\UserDefaultPageConfigEntityForm",
 *       "edit" = "Drupal\user_default_page\Form\UserDefaultPageConfigEntityForm",
 *       "delete" = "Drupal\user_default_page\Form\UserDefaultPageConfigEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\user_default_page\UserDefaultPageConfigEntityHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "user_default_page_config_entity",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "user_roles",
 *     "users",
 *     "login_redirect",
 *     "login_redirect_message",
 *     "logout_redirect",
 *     "logout_redirect_message"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/people/user_default_page/{user_default_page_config_entity}",
 *     "add-form" = "/admin/config/people/user_default_page/add",
 *     "edit-form" = "/admin/config/people/user_default_page/{user_default_page_config_entity}/edit",
 *     "delete-form" = "/admin/config/people/user_default_page/{user_default_page_config_entity}/delete",
 *     "collection" = "/admin/config/people/user_default_page"
 *   }
 * )
 */
class UserDefaultPageConfigEntity extends ConfigEntityBase implements UserDefaultPageConfigEntityInterface {
  /**
   * The User default page ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The User default page label.
   *
   * @var string
   */
  protected $label;

  /**
   * The User default page users.
   *
   * @var string
   */
  protected $users;

  /**
   * The User default page user roles.
   *
   * @var string
   */
  protected $user_roles;

  /**
   * The User default page login redirect.
   *
   * @var string
   */
  protected $login_redirect;

  /**
   * The User default page login redirect message.
   *
   * @var string
   */
  protected $login_redirect_message;

  /**
   * The User default page logout redirect.
   *
   * @var string
   */
  protected $logout_redirect;

  /**
   * The User default page logout redirect message.
   *
   * @var string
   */
  protected $logout_redirect_message;

  /**
   * The weight of the default page.
   *
   * @var int
   */
  protected $weight;

  /**
   * {@inheritdoc}
   */
  public function getUserRoles() {
    return $this->user_roles;
  }

  /**
   * {@inheritdoc}
   */
  public function getUsers() {
    return $this->users;
  }

  /**
   * {@inheritdoc}
   */
  public function setUsers($users) {
    $this->set('users', $users);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLoginRedirect() {
    return $this->login_redirect;
  }

  /**
   * {@inheritdoc}
   */
  public function getLoginRedirectMessage() {
    return $this->login_redirect_message;
  }

  /**
   * {@inheritdoc}
   */
  public function getLogoutRedirect() {
    return $this->logout_redirect;
  }

  /**
   * {@inheritdoc}
   */
  public function getLogoutRedirectMessage() {
    return $this->logout_redirect_message;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->weight;
  }

}
