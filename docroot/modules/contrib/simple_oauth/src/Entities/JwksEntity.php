<?php

namespace Drupal\simple_oauth\Entities;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\File\FileSystemInterface;

/**
 * A JSON Web Key Store entity.
 */
class JwksEntity implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected FileSystemInterface $fileSystem,
  ) {
  }

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
    $config = $this->configFactory->get('simple_oauth.settings');
    $public_key_real = $this->fileSystem->realpath($config->get('public_key'));
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
