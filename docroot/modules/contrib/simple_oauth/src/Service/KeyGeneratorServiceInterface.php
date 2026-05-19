<?php

namespace Drupal\simple_oauth\Service;

/**
 * An interface to generates the signature keys.
 */
interface KeyGeneratorServiceInterface {

  /**
   * Generate both public key and private key on the given paths.
   *
   * If no public path is given, then the private path is going to be use for
   * both keys.
   *
   * @param string $dir_path
   *   Private key path.
   *
   * @throws \Drupal\simple_oauth\Service\Exception\ExtensionNotLoadedException
   * @throws \Drupal\simple_oauth\Service\Exception\FilesystemValidationException
   */
  public function generateKeys(string $dir_path): void;

}
