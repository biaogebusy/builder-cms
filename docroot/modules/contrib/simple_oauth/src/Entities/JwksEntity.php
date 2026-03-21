<?php

namespace Drupal\simple_oauth\Entities;

/**
 * A JSON Web Key Store entity.
 */
class JwksEntity {

  /**
   * Returns the keys in JWK (JSON Web Key) format.
   *
   * @see https://tools.ietf.org/html/rfc7517
   *
   * @return array
   *   List of keys.
   */
  public function getKeys() {
    $json_data = [];
    // Get the public key from simple_oauth settings.
    $config = \Drupal::config('simple_oauth.settings');
    $public_key_real = \Drupal::service('file_system')->realpath($config->get('public_key'));
    if (!empty($public_key_real)) {
      $key_info = openssl_pkey_get_details(openssl_pkey_get_public(file_get_contents($public_key_real)));
      $json_data = [
        'keys' => [
          [
            'kty' => 'RSA',
            'n' => rtrim(str_replace(['+', '/'], ['-', '_'], base64_encode($key_info['rsa']['n'])), '='),
            'e' => rtrim(str_replace(['+', '/'], ['-', '_'], base64_encode($key_info['rsa']['e'])), '='),
          ],
        ],
      ];
    }

    return $json_data;
  }

}
