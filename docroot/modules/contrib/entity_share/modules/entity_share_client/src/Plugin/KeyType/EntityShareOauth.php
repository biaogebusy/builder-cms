<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\KeyType;

use Drupal\key\Plugin\KeyType\AuthenticationMultivalueKeyType;

/**
 * Key module plugin to define an oauth credentials KeyType.
 *
 * @KeyType(
 *   id = "entity_share_oauth",
 *   label = @Translation("Entity Share Oauth"),
 *   description = @Translation("A key type to store oauth credentials for the Entity Share module. Store as JSON:<br><pre>{<br>&quot;client_id&quot;: &quot;client_id value&quot;,<br>&quot;client_secret&quot;: &quot;client_secret value&quot;<br>,<br>&quot;authorization_path&quot;: &quot;authorization_path value&quot;<br>,<br>&quot;token_path&quot;: &quot;token_path value&quot;<br>}</pre>"),
 *   group = "authentication",
 *   key_value = {
 *     "plugin" = "textarea_field"
 *   },
 *   multivalue = {
 *     "enabled" = true,
 *     "fields" = {
 *       "client_id" = @Translation("Client ID"),
 *       "client_secret" = @Translation("Client Secret"),
 *       "authorization_path" = @Translation("Authorization Path"),
 *       "token_path" = @Translation("Token Path")
 *     }
 *   }
 * )
 */
class EntityShareOauth extends AuthenticationMultivalueKeyType {

}
