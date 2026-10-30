<?php

declare(strict_types=1);

namespace Drupal\xinshi_credits;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Psr\Log\LoggerInterface;

/** Bounded expiry work stays enabled when new grants or spending are paused. */
final class CreditExpiry {

  public function __construct(
    private readonly Connection $database,
    private readonly CreditContext $context,
    private readonly CreditLifecycle $lifecycle,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
  ) {}

  /** Process at most fifty due grants without changing unresolved execution holds. */
  public function process(): void {
    try { $site = $this->context->site(); }
    catch (CreditException) { return; }
    $query = $this->database->select('credit_grant', 'g')->fields('g', ['id', 'expires_at']);
    $query->join('credit_account', 'a', 'a.id = g.account_id');
    $query->addField('a', 'owner_id');
    $query->join('credit_grant_cost', 'c', 'c.grant_id = g.id');
    $query->condition('g.site_id', $site)->condition('g.expires_at', $this->time->getCurrentTime(), '<=');
    $query->condition($query->orConditionGroup()->condition('g.available_atoms', 0, '>')
      ->condition('c.committed_micros', 0, '>')->condition('c.reserved_micros', 0, '>'));
    foreach ($query->distinct()->orderBy('g.expires_at')->orderBy('g.id')->range(0, 50)->execute() as $grant) {
      try {
        // A canonical reason keeps a repeated system command independent of request language.
        $this->lifecycle->expire(['beneficiary_user_id' => (string) $grant->owner_id, 'actor_user_id' => '0',
          'grant_id' => $grant->id, 'public_reason' => 'Credit grant expired'], 'expiry:' . $grant->id);
      }
      catch (\Throwable $error) {
        $this->logger->error('Credit expiry failed for @grant with @class.', ['@grant' => $grant->id, '@class' => $error::class]);
      }
    }
  }

}
