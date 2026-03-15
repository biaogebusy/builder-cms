<?php

namespace Drupal\social_auth_github\Plugin\Network;

use Drupal\social_auth\Plugin\Network\NetworkBase;
use Drupal\social_auth\Plugin\Network\NetworkInterface;

/**
 * Defines a Network Plugin for Social Auth GitHub.
 *
 * @package Drupal\social_auth_github\Plugin\Network
 *
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
 */
class GitHubAuth extends NetworkBase implements NetworkInterface {}
