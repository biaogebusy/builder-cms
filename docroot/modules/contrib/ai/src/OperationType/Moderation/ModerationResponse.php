<?php

namespace Drupal\ai\OperationType\Moderation;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Complex object for the moderation response.
 */
class ModerationResponse {

  /**
   * The answer for the moderation. True is flagged.
   *
   * @var bool
   */
  private bool $flagged;

  /**
   * The verbose information.
   *
   * @var array
   */
  private array $information;

  /**
   * A user-facing message about the result of the moderation.
   *
   * @var \Drupal\Core\StringTranslation\TranslatableMarkup|null
   */
  private ?TranslatableMarkup $message;

  /**
   * The constructor.
   *
   * @param bool $flagged
   *   The flagged status.
   * @param array $information
   *   The verbose information if any.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $message
   *   The message to set or NULL.
   */
  public function __construct(bool $flagged, array $information = [], ?TranslatableMarkup $message = NULL) {
    $this->flagged = $flagged;
    $this->information = $information;
    $this->message = $message;
  }

  /**
   * Get the role of the message.
   *
   * @return string
   *   The role.
   */
  public function isFlagged(): string {
    return $this->flagged;
  }

  /**
   * Get the information.
   *
   * @return array
   *   The information.
   */
  public function getInformation(): array {
    return $this->information;
  }

  /**
   * Get the message, if set.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The message or NULL on error.
   */
  public function getMessage(): ?TranslatableMarkup {
    return $this->message;
  }

  /**
   * Set if the message is flagged.
   *
   * @param bool $flagged
   *   The flagged status.
   */
  public function setFlagged(bool $flagged): void {
    $this->flagged = $flagged;
  }

  /**
   * Set the information.
   *
   * @param array $information
   *   The information.
   */
  public function setInformation(array $information): void {
    $this->information = $information;
  }

  /**
   * Set the message.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   The message to set.
   */
  public function setMessage(TranslatableMarkup $message): void {
    $this->message = $message;
  }

}
