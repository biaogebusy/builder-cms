<?php

declare(strict_types = 1);

namespace Drupal\sms;

/**
 * Contains direction constants for SMS Framework.
 */
final class Direction {

  /**
   * Whether the message is queued to be sent from the website.
   *
   * @var int
   */
  public const OUTGOING = 1;

  /**
   * Whether the message was received by the website.
   *
   * @var int
   */
  public const INCOMING = -1;

}
