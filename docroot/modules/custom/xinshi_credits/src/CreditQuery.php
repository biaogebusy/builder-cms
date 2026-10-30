<?php

declare(strict_types=1);

namespace Drupal\xinshi_credits;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Session\AccountProxyInterface;

/** Authorized projections and notification acknowledgement, with no grant side effects. */
final class CreditQuery {

  public function __construct(
    private readonly Connection $database,
    private readonly CreditContext $context,
    private readonly TimeInterface $time,
    private readonly PrivateKey $privateKey,
    private readonly AccountProxyInterface $currentUser,
    private readonly EntityTypeManagerInterface $entities,
  ) {}

  /** Return a balance at its committed version, or an explicit unavailable state. */
  public function account(?string $target = NULL): array {
    $account = $this->authorizedAccount($target);
    $now = $this->time->getCurrentTime();
    if (!$account) return ['status' => 'not_initialized', 'data_as_of' => gmdate('c', $now)];
    $result = ['status' => $account['status'], 'account_id' => $account['id'],
      'balance_version' => (string) $account['balance_version'], 'data_as_of' => gmdate('c', $now),
      'ledger_watermark' => $this->watermark($account)];
    if ($account['status'] !== 'active') return $result;
    $expired = $this->database->select('credit_grant', 'g')->fields('g', ['id'])
      ->condition('account_id', $account['id'])->condition('expires_at', $now, '<=')
      ->condition('available_atoms', 0, '>')->range(0, 1)->execute()->fetchField();
    // Cron may lag behind the clock; reads must never expose stale spendable funds.
    if ($expired) return array_replace($result, ['status' => 'expiry_pending']);
    $active = 0;
    foreach (['available_atoms', 'held_atoms', 'refund_locked_atoms'] as $field) {
      $value = CreditValue::amount((string) $account[$field]);
      $result[$field] = (string) $value;
      $active = CreditValue::add($active, $value);
    }
    $result['active_balance_atoms'] = (string) $active;
    return $result;
  }

  /** Paginate an immutable journal window with a principal-bound signed cursor. */
  public function transactions(array $input, ?string $target = NULL): array {
    CreditValue::fields($input, ['from', 'to'], ['kind', 'limit', 'cursor']);
    $from = $this->timestamp($input['from']);
    $to = $this->timestamp($input['to']);
    if ($from >= $to || $to - $from > 366 * 86400) throw new CreditException('invalid_period');
    if (isset($input['kind']) && !in_array($input['kind'], ['grant', 'hold', 'release', 'consume', 'return', 'expire'], TRUE)) {
      throw new CreditException('invalid_kind');
    }
    $limit = CreditValue::amount($input['limit'] ?? '50', TRUE);
    if ($limit > 100) throw new CreditException('invalid_limit');
    $account = $this->authorizedAccount($target);
    if (!$account) {
      if (isset($input['cursor'])) throw new CreditException('invalid_cursor');
      return ['status' => 'not_initialized', 'rows' => [], 'next_cursor' => NULL, 'ledger_watermark' => '0'];
    }
    if ($account['status'] !== 'active') throw new CreditException('account_unavailable', 409);
    $binding = hash('sha256', json_encode([$account['site_id'], $account['id'], $from, $to,
      $input['kind'] ?? NULL, $limit, $target === NULL ? NULL : (string) $this->currentUser->id()], JSON_THROW_ON_ERROR));
    $watermark = $this->watermark($account);
    $before = NULL;
    if (isset($input['cursor'])) {
      [$watermark, $before] = $this->decodeCursor($input['cursor'], $binding);
    }
    $query = $this->database->select('credit_journal', 'j');
    $query->leftJoin('credit_grant', 'g', 'g.id = j.grant_id');
    $query->fields('j', ['sequence', 'id', 'transaction_kind', 'grant_id', 'available_delta_atoms',
      'available_after_atoms', 'held_delta_atoms', 'held_after_atoms', 'refund_locked_delta_atoms',
      'refund_locked_after_atoms', 'operation_id', 'reverses_journal_id', 'balance_version', 'public_reason', 'posted_at']);
    $query->fields('g', ['source_kind', 'grant_kind', 'rule_id', 'rule_version', 'scope_json', 'expires_at']);
    $query->condition('j.account_id', $account['id'])->condition('j.site_id', $account['site_id'])
      ->condition('j.posted_at', $from, '>=')->condition('j.posted_at', $to, '<')
      ->condition('j.sequence', $watermark, '<=');
    if ($before !== NULL) $query->condition('j.sequence', $before, '<');
    if (isset($input['kind'])) $query->condition('j.transaction_kind', $input['kind']);
    $rows = $query->orderBy('j.sequence', 'DESC')->range(0, $limit + 1)->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $more = count($rows) > $limit;
    $rows = array_slice($rows, 0, $limit);
    $next = $more ? $this->encodeCursor($binding, $watermark, (string) end($rows)['sequence']) : NULL;
    foreach ($rows as &$row) {
      $row['journal_id'] = $row['id'];
      unset($row['id'], $row['sequence']);
      foreach (['available_delta_atoms', 'available_after_atoms', 'held_delta_atoms', 'held_after_atoms',
        'refund_locked_delta_atoms', 'refund_locked_after_atoms', 'balance_version'] as $field) $row[$field] = (string) $row[$field];
      foreach (['posted_at', 'expires_at'] as $field) if ($row[$field] !== NULL) $row[$field] = CreditValue::expiryIso($row[$field]);
      $row['scope'] = $row['scope_json'] === NULL ? NULL : json_decode($row['scope_json'], TRUE, 16, JSON_THROW_ON_ERROR);
      unset($row['scope_json']);
    }
    return ['status' => 'complete', 'rows' => $rows, 'next_cursor' => $next,
      'ledger_watermark' => $watermark, 'data_as_of' => gmdate('c', $this->time->getCurrentTime())];
  }

  /** Return pending in-app events with only the recipient's public ledger fields. */
  public function notifications(): array {
    $account = $this->authorizedAccount(NULL);
    if (!$account) return ['rows' => []];
    $query = $this->database->select('credit_notification_outbox', 'n');
    $query->innerJoin('credit_journal', 'j', 'j.id = n.journal_id');
    $query->innerJoin('credit_grant', 'g', 'g.id = j.grant_id');
    $query->fields('n', ['event_id', 'journal_id']);
    $query->fields('j', ['public_reason', 'available_delta_atoms', 'posted_at']);
    $query->fields('g', ['grant_kind']);
    $rows = $query->condition('n.recipient_user_id', (string) $this->currentUser->id())
      ->condition('n.state', 'pending')->condition('j.account_id', $account['id'])
      ->condition('j.site_id', $account['site_id'])->orderBy('j.sequence')->range(0, 20)
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
      $row['available_delta_atoms'] = (string) $row['available_delta_atoms'];
      $row['posted_at'] = gmdate('c', (int) $row['posted_at']);
    }
    return ['rows' => $rows];
  }

  /** Acknowledge one owned event without changing the grant or issuing another event. */
  public function acknowledge(string $event): array {
    $account = $this->authorizedAccount(NULL);
    CreditValue::id($event);
    if (!$account) throw new CreditException('notification_not_found', 404);
    $query = $this->database->select('credit_notification_outbox', 'n');
    $query->innerJoin('credit_journal', 'j', 'j.id = n.journal_id');
    $found = $query->fields('n', ['event_id'])->condition('n.event_id', $event)
      ->condition('n.recipient_user_id', (string) $this->currentUser->id())
      ->condition('j.account_id', $account['id'])->condition('j.site_id', $account['site_id'])
      ->execute()->fetchField();
    if (!$found) throw new CreditException('notification_not_found', 404);
    $this->database->update('credit_notification_outbox')->fields(['state' => 'acknowledged'])
      ->condition('event_id', $event)->condition('recipient_user_id', (string) $this->currentUser->id())->execute();
    return ['status' => 'acknowledged', 'event_id' => $event];
  }

  private function authorizedAccount(?string $target): array|false {
    // Keep the OAuth principal for access; load the user only to check current account status.
    if (!$this->currentUser->isAuthenticated()
      || !$this->currentUser->hasPermission($target === NULL ? 'view own credits' : 'view user credits')) {
      throw new CreditException('permission_denied', 403);
    }
    $user = $this->entities->getStorage('user')->load($this->currentUser->id());
    if (!$user || !$user->isActive()) throw new CreditException('permission_denied', 403);
    if ($target !== NULL) {
      CreditValue::amount($target, TRUE);
      $subject = $this->entities->getStorage('user')->load($target);
      if (!$subject || !$subject->isActive() || !$subject->access('view', $this->currentUser)) {
        throw new CreditException('permission_denied', 403);
      }
    }
    return $this->database->select('credit_account', 'a')->fields('a')
      ->condition('site_id', $this->context->site())->condition('owner_id', $target ?? (string) $this->currentUser->id())
      ->execute()->fetchAssoc();
  }

  private function watermark(array $account): string {
    $query = $this->database->select('credit_journal', 'j')->condition('account_id', $account['id'])
      ->condition('balance_version', $account['balance_version'], '<=');
    $query->addExpression('MAX(sequence)');
    return (string) ($query->execute()->fetchField() ?: '0');
  }

  private function timestamp(mixed $value): int {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/D', $value)) {
      throw new CreditException('invalid_period');
    }
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $value);
    $errors = \DateTimeImmutable::getLastErrors();
    if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $date->getTimestamp() < 0
      || !preg_match('/(Z|[+-](0[0-9]|1[0-4]):[0-5][0-9])$/D', $value)) throw new CreditException('invalid_period');
    return $date->getTimestamp();
  }

  private function encodeCursor(string $binding, string $watermark, string $before): string {
    $body = $binding . '.' . $watermark . '.' . $before;
    return $body . '.' . hash_hmac('sha256', 'credits.cursor.v1:' . $body, $this->privateKey->get());
  }

  private function decodeCursor(mixed $value, string $binding): array {
    if (!is_string($value) || strlen($value) > 200) throw new CreditException('invalid_cursor');
    $parts = explode('.', $value);
    if (count($parts) !== 4 || !hash_equals($parts[0], $binding)
      || !hash_equals($this->encodeCursor($parts[0], $parts[1], $parts[2]), $value)) {
      throw new CreditException('invalid_cursor');
    }
    $watermark = CreditValue::amount($parts[1], TRUE);
    $before = CreditValue::amount($parts[2], TRUE);
    if ($before > $watermark) throw new CreditException('invalid_cursor');
    return [(string) $watermark, (string) $before];
  }

}
