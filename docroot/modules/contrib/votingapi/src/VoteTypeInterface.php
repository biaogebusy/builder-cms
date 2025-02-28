<?php

namespace Drupal\votingapi;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a vote type entity.
 */
interface VoteTypeInterface extends ConfigEntityInterface {

  /**
   * Returns the description.
   *
   * @return string
   *   The description of this vote type.
   */
  public function getDescription();

  /**
   * Returns the type of vote value. (e.g. points, percentage, etc.)
   *
   * @return string
   *   The type of vote value.
   */
  public function getValueType();

}
