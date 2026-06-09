<?php

namespace Drupal\ai\Traits\File;

use Symfony\Component\Mime\MimeTypeGuesserInterface;

/**
 * Trait to add the possibility to get the file mime type guesser.
 *
 * @package Drupal\ai\Traits\File
 */
trait FileMimeTypeTrait {

  /**
   * {@inheritdoc}
   */
  public function getFileMimeTypeGuesser(): MimeTypeGuesserInterface {
    return \Drupal::service('file.mime_type.guesser');
  }

}
