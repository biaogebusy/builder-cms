<?php

namespace Drupal\private_message\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Private message thread constraint validator.
 *
 * Ensures that all members of a private message thread have permission to use
 * the private message thread.
 */
class PrivateMessageThreadMemberConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    $users = $items->referencedEntities();
    foreach ($users as $user) {
      if (!$user->hasPermission('use private messaging system')) {
        $this->context->addViolation($constraint->userPrivateMessagePermissionError, ['%user' => $user->getDisplayName()]);
      }
    }
  }

}
