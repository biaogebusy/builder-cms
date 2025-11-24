<?php

declare(strict_types=1);

namespace Drupal\private_message\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\private_message\Entity\PrivateMessageBan;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Constraint validator for a unique bans.
 */
class UniqueBanConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    assert($value instanceof PrivateMessageBan);

    $storage = $this->entityTypeManager->getStorage('private_message_ban');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('owner', $value->getOwnerId(), '=')
      ->condition('target', $value->getTargetId(), '=');

    if ($query->range(0, 1)->execute()) {
      $this->context->buildViolation($constraint->message, [
        '%user' => $value->getTarget()->getDisplayName(),
      ])->addViolation();
    }
  }

}
