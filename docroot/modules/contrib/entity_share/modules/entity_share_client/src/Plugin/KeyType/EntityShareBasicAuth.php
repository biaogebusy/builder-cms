<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\KeyType;

use Drupal\key\Plugin\KeyType\AuthenticationMultivalueKeyType;

/**
 * Key module plugin to define a basic_auth credentials KeyType.
 *
 * @KeyType(
 *   id = "entity_share_basic_auth",
 *   label = @Translation("Entity Share Basic Auth"),
 *   description = @Translation("A key type to store Basic Auth credentials for the Entity Share module. Store as JSON:<br><pre>{<br>&quot;username&quot;: &quot;username value&quot;,<br>&quot;password&quot;: &quot;password value&quot;<br>}</pre>"),
 *   group = "authentication",
 *   key_value = {
 *     "plugin" = "textarea_field"
 *   },
 *   multivalue = {
 *     "enabled" = true,
 *     "fields" = {
 *       "username" = @Translation("Username"),
 *       "password" = @Translation("Password")
 *     }
 *   }
 * )
 */
class EntityShareBasicAuth extends AuthenticationMultivalueKeyType {

}
