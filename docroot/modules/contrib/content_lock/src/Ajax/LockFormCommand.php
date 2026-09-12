<?php

namespace Drupal\content_lock\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Defines an AJAX command to lock current form.
 *
 * @ingroup ajax
 */
class LockFormCommand implements CommandInterface {

  /**
   * LockFormCommand constructor.
   *
   * @param bool $lockable
   *   Whether the form is lockable.
   * @param bool $lock
   *   Whether to lock the form.
   */
  public function __construct(
    protected bool $lockable = FALSE,
    protected bool $lock = FALSE,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    return [
      'command' => 'lockForm',
      'selector' => '',
      'lockable' => $this->lockable,
      'lock' => $this->lock,
    ];
  }

}
