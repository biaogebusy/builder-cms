<?php

namespace Drupal\ai\OperationType;

/**
 * Data transfer object interface.
 */
interface OutputInterface {

  /**
   * Get the normalized output.
   *
   * @return mixed
   *   The normalized output. Mixed or a parent Output.
   */
  public function getNormalized();

  /**
   * Get the raw output.
   *
   * @return mixed
   *   The raw output. Usually an array or object.
   */
  public function getRawOutput();

  /**
   * Get the metadata.
   *
   * @return mixed
   *   The metadata. Mixed or a parent Output.
   */
  public function getMetadata();

  /**
   * Convert the object to an array.
   *
   * @return array
   *   The object as an array.
   */
  public function toArray(): array;

}
