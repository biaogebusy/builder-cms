<?php

namespace Drupal\social_auth_google\Plugin\Network;

use Drupal\social_auth\Plugin\Network\NetworkBase;
use Drupal\social_auth\Plugin\Network\NetworkInterface;

/**
 * Defines a Network Plugin for Social Auth Google.
 *
 * @package Drupal\social_auth_google\Plugin\Network
 *
 * @Network(
 *   id = "social_auth_google",
 *   short_name = "google",
 *   social_network = "Google",
 *   img_path = "img/google_logo.svg",
 *   type = "social_auth",
 *   class_name = "\League\OAuth2\Client\Provider\Google",
 *   auth_manager = "\Drupal\social_auth_google\GoogleAuthManager",
 *   routes = {
 *     "redirect": "social_auth.network.redirect",
 *     "callback": "social_auth.network.callback",
 *     "settings_form": "social_auth.network.settings_form",
 *   },
 *   handlers = {
 *     "settings": {
 *       "class": "\Drupal\social_auth_google\Settings\GoogleAuthSettings",
 *       "config_id": "social_auth_google.settings"
 *     }
 *   }
 * )
 */
class GoogleAuth extends NetworkBase implements NetworkInterface {

  /**
   * {@inheritdoc}
   */
  protected function getExtraSdkSettings(): array {
    $hosted_domain = $this->settings->getRestrictedDomain();
    return [
      'accessType' => 'offline',
      'verify' => FALSE,
      'hostedDomain' => empty($hosted_domain) ? NULL : $hosted_domain,
    ];
  }

}
