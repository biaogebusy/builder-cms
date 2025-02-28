<?php

namespace Drupal\acquia_connector;

use Drupal\Core\Password\PhpassHashedPassword;

/**
 * Class CryptConnector.
 *
 * Extends secure password hashing functions based on the Portable PHP password
 * hashing framework.
 *
 * @package Drupal\acquia_connector
 */
class CryptConnector extends PhpassHashedPassword {

  /**
   * The string name of a hashing algorithm usable by hash(), like 'sha256'.
   *
   * @var string
   */
  private $algo;

  /**
   * Plain-text password up to 512 bytes (128 to 512 UTF-8 characters) to hash.
   *
   * @var string
   */
  private $password;

  /**
   * An existing hash or the output of $this->generateSalt().
   *
   * @var string
   */
  private $setting;

  /**
   * CryptConnector constructor.
   *
   * @param string $algo
   *   The string name of a hashing algorithm usable by hash(), like 'sha256'.
   * @param string $password
   *   Plain-text password up to 512 bytes (128 to 512 UTF-8 characters) to
   *   hash.
   * @param string $setting
   *   An existing hash or the output of $this->generateSalt(). Must be at least
   *   12 characters (the settings and salt).
   * @param mixed $extra_md5
   *   (Deprecated) If not empty password needs to be hashed with MD5 first.
   */
  public function __construct($algo, $password, $setting, $extra_md5) {
    $this->algo = $algo;
    $this->password = $password;
    $this->setting = $setting;
  }

  /**
   * Crypt pass.
   *
   * @return string
   *   Crypt password.
   */
  public function cryptPass() {
    $crypt_pass = $this->crypt($this->algo, $this->password, $this->setting);

    return $crypt_pass;
  }

  /**
   * Helper function. Calculate sha1 hash.
   *
   * @param string $key
   *   Acquia Subscription Key.
   * @param string $string
   *   String to calculate hash.
   *
   * @return string
   *   Sha1 string.
   */
  public static function acquiaHash($key, $string) {
    return sha1((str_pad($key, 64, chr(0x00)) ^ (str_repeat(chr(0x5c), 64))) . pack("H*", sha1((str_pad($key, 64, chr(0x00)) ^ (str_repeat(chr(0x36), 64))) . $string)));
  }

  /**
   * Derive a key for the solr hmac using a salt, id and key.
   *
   * @param string $salt
   *   Salt.
   * @param string $id
   *   Acquia Subscription ID.
   * @param string $key
   *   Acquia Subscription Key.
   *
   * @return string
   *   Derived Key.
   */
  public static function createDerivedKey($salt, $id, $key) {
    $derivation_string = $id . 'solr' . $salt;
    return hash_hmac('sha1', str_pad($derivation_string, 80, $derivation_string), $key);
  }

}
