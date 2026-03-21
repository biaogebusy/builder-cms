<?php

use Drupal\simple_oauth\Entities\AccessTokenEntity;
use Drupal\user\Entity\User;
/**
 * @file
 * Hooks specific to the Simple OAuth module.
 */

/**
 * @defgroup simple_oauth Simple Oauth: Hooks
 * @{
 */

use Drupal\user\UserInterface;

/**
 * Alter the private claims to prepare convert to JWT token.
 *
 * @param $private_claims
 *   The private claims array to be altered.
 * @param \Drupal\simple_oauth\Entities\AccessTokenEntity $access_token_entity
 *
 * @see \Drupal\simple_oauth\Entities\AccessTokenEntity::convertToJWT()
 */
function hook_simple_oauth_private_claims_alter(&$private_claims, AccessTokenEntity $access_token_entity) {
  $user_id = $access_token_entity->getUserIdentifier();
  $user = User::load($user_id);
  $private_claims = [
    'mail' => $user->getEmail(),
    'username' => $user->getAccountName(),
  ];
}

/**
 * Allow injecting custom claims to the OpenID Connect responses.
 *
 * This will allow sites to connect their custom implementations and data model
 * to the different claims supported by OpenID Connect.
 *
 * @param array $private_claims
 *   The private claims array to be altered.
 * @param array $context
 *   Additional information relevant to the custom claims. It contains:
 *     - 'account': The TokenAuthUserInterface object decorating the user.
 *     - 'claims': The claim names to serve to the users. If you add new claims
 *       you will need to add them in your service container under the key
 *       'simple_oauth.openid.claims'.
 *
 * @see \Drupal\simple_oauth\Entities\AccessTokenEntity::convertToJWT()
 */
function hook_simple_oauth_oidc_claims_alter(array &$claim_values, array &$context) {
  $account = $context['account'];
  assert($account instanceof UserInterface);
  $value = $account->get('field_phone_number')->getValue();
  $claim_values['phone_number'] = $value[0]['value'] ?? NULL;
}

/**
 * @} End of "defgroup simple_oauth".
 */
