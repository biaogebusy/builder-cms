<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Plugin\KeyType;

use Drupal\key\Plugin\KeyType\AuthenticationMultivalueKeyType;

/**
 * Key module plugin to define a header credentials KeyType.
 *
 * @KeyType(
 *   id = "entity_share_header",
 *   label = @Translation("Entity Share Header"),
 *   description = @Translation("A key type to store Header data for the Entity Share module. Store as JSON:<br><pre>{<br>&quot;header_name&quot;: &quot;header name&quot;,<br>&quot;header_value&quot;: &quot;header value&quot;<br>}</pre>"),
 *   group = "authentication",
 *   key_value = {
 *     "plugin" = "textarea_field"
 *   },
 *   multivalue = {
 *     "enabled" = true,
 *     "fields" = {
 *       "header_name" = @Translation("Header name"),
 *       "header_value" = @Translation("Header value")
 *     }
 *   }
 * )
 */
class EntityShareHeader extends AuthenticationMultivalueKeyType {

}
