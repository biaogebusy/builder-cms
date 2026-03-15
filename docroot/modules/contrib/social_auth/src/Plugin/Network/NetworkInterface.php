<?php

namespace Drupal\social_auth\Plugin\Network;

use Drupal\Core\Url;
use Drupal\social_api\Plugin\NetworkInterface as NetworkInterfaceBase;

/**
 * Defines an interface for Social Auth Network.
 *
 * Example network annotation (from GitHub implementor):
 *
 * @code
 * @Network(
 *   id = "social_auth_github",
 *   short_name = "github",
 *   social_network = "GitHub",
 *   img_path = "img/github_logo.svg",
 *   type = "social_auth",
 *   class_name = "\League\OAuth2\Client\Provider\Github",
 *   auth_manager = "\Drupal\social_auth_github\GitHubAuthManager",
 *   routes = {
 *     "redirect": "social_auth.network.redirect",
 *     "callback": "social_auth.network.callback",
 *     "settings_form": "social_auth.network.settings_form",
 *   },
 *   handlers = {
 *     "settings": {
 *       "class": "\Drupal\social_auth\Settings\SettingsBase",
 *       "config_id": "social_auth_github.settings"
 *     }
 *   }
 * )
 * @endcode
 */
interface NetworkInterface extends NetworkInterfaceBase {

  /**
   * Gets the network ID.
   *
   * @return string
   *   Network ID.
   */
  public function getId(): string;

  /**
   * Gets the network short name.
   *
   * @return string
   *   Network short name.
   */
  public function getShortName(): string;

  /**
   * Gets the social network name.
   *
   * @return string
   *   Social network name.
   */
  public function getSocialNetwork(): string;

  /**
   * Gets the network image path.
   *
   * @return string
   *   Gets the relative path to the network image.
   */
  public function getImagePath(): string;

  /**
   * Gets the network type.
   *
   * @return string
   *   Network type.
   */
  public function getType(): string;

  /**
   * Gets the class name for the network provider.
   *
   * @return string
   *   Network provider class name.
   */
  public function getProviderClassName(): string;

  /**
   * Gets the network authorization manager class name.
   *
   * @return string
   *   Network authorization manager class name.
   */
  public function getAuthManagerClassName(): string;

  /**
   * Gets the network routes list.
   *
   * @return array
   *   Network routes. Expected array keys for routes are:
   *    - redirect,
   *    - callback, and
   *    - settings_form.
   */
  public function getRoutes(): array;

  /**
   * Gets the network handlers.
   *
   * @return array
   *   Network handlers. Expected array keys are:
   *    - settings
   *      - class
   *      - config_id
   */
  public function getHandlers(): array;

  /**
   * Gets the network's redirect URL.
   *
   * @param array $route_options
   *   Additional options for the route.
   *
   * @return \Drupal\Core\Url
   *   Redirect URL for the network.
   */
  public function getRedirectUrl(array $route_options = []): Url;

  /**
   * Gets the network's callback URL.
   *
   * @param array $route_options
   *   Additional options for the route.
   *
   * @return \Drupal\Core\Url
   *   Callback URL for the network.
   */
  public function getCallbackUrl(array $route_options = []): Url;

  /**
   * Gets the network's settings form URL.
   *
   * @param array $route_options
   *   Additional options for the route.
   *
   * @return \Drupal\Core\Url
   *   Settings URL for the network.
   */
  public function getSettingsFormUrl(array $route_options = []): Url;

  /**
   * Gets the full path of the provider logo for the network.
   *
   * This path is used to render log in buttons with images in the provided
   * login block.
   *
   * @return string
   *   Full path to the network logo.
   *
   * @see \Drupal\social_auth\Plugin\Block\SocialAuthLoginBlock
   */
  public function getProviderLogoPath(): string;

}
