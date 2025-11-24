<?php

declare(strict_types=1);

namespace Drupal\private_message\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validation constraint for unique bans.
 */
#[Constraint(
  id: 'UniquePrivateMessageBan',
  label: new TranslatableMarkup('Unique ban', [], ['context' => 'Validation']),
)]

class UniqueBanConstraint extends SymfonyConstraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public string $message = 'The user %user is already banned.';

}
