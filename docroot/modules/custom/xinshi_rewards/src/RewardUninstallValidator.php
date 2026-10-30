<?php

declare(strict_types=1);

namespace Drupal\xinshi_rewards;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/** Retain daily deduplication and accepted request identities when admission pauses. */
final class RewardUninstallValidator implements ModuleUninstallValidatorInterface {

  public function __construct(private readonly Connection $database) {}

  /** {@inheritdoc} */
  public function validate($module): array {
    if ($module === 'xinshi_rewards' && $this->database->schema()->tableExists('credit_reward_claim')
      && $this->database->select('credit_reward_claim', 'c')->fields('c', ['id'])->range(0, 1)->execute()->fetchField()) {
      return [new TranslatableMarkup('Reward qualifications and accepted requests must be retained. Pause admission in configuration.')];
    }
    return [];
  }

}
