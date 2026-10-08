<?php

declare(strict_types=1);

namespace Drupal\xinshi_knowledge_sync\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/** Resolves deployment-owned policies; manifests cannot grant document access. */
final class SourceAccess {

  public const TABLE = 'xinshi_knowledge_sync_document';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
  ) {}

  /** Returns an enabled source, failing closed for unknown configuration. */
  public function source(string $id): array {
    $matches = array_values(array_filter($this->sources(), fn(array $source) => ($source['id'] ?? NULL) === $id));
    if (count($matches) !== 1 || ($matches[0]['enabled'] ?? FALSE) !== TRUE) {
      throw new \DomainException('unknown_or_disabled_source');
    }
    $source = $matches[0];
    $this->definition($source, $source['default_policy'] ?? '');
    $prefixes = [];
    foreach ($source['rules'] ?? [] as $rule) {
      $prefix = $rule['prefix'] ?? '';
      if (!is_string($prefix) || $prefix === '' || !str_ends_with($prefix, '/') ||
          str_starts_with($prefix, '/') || str_contains($prefix, '\\') ||
          in_array('..', explode('/', $prefix), TRUE) || isset($prefixes[$prefix])) {
        throw new \DomainException('invalid_source_rule');
      }
      $prefixes[$prefix] = TRUE;
      $this->definition($source, $rule['policy'] ?? '');
    }
    return $source;
  }

  /** Selects the longest matching path prefix from administrator configuration. */
  public function policy(string $sourceId, string $path): string {
    $source = $this->source($sourceId);
    $policy = $source['default_policy'] ?? '';
    $length = -1;
    foreach ($source['rules'] ?? [] as $rule) {
      $prefix = $rule['prefix'] ?? '';
      if (str_starts_with($path, $prefix) && strlen($prefix) > $length) {
        $policy = $rule['policy'] ?? '';
        $length = strlen($prefix);
      }
    }
    $this->definition($source, $policy);
    return $policy;
  }

  /** Returns the ownership row for a synchronized node, if any. */
  public function document(int $nid): ?array {
    return $this->database->select(self::TABLE, 'd')->fields('d')->condition('nid', $nid)
      ->execute()->fetchAssoc() ?: NULL;
  }

  /** Checks live policy membership in addition to the existing knowledge permission. */
  public function allowed(string $source, string $policy, AccountInterface $account): bool {
    if (!$account->isAuthenticated() || !$account->hasPermission('view xinshi knowledge')) {
      return FALSE;
    }
    $user = $this->entities->getStorage('user')->load($account->id());
    if (!$user || !$user->isActive()) {
      return FALSE;
    }
    try {
      $definition = $this->definition($this->source($source), $policy);
      return ($definition['all_readers'] ?? FALSE) === TRUE ||
        (bool) array_intersect($account->getRoles(), $definition['roles'] ?? []) ||
        in_array((int) $account->id(), $definition['users'] ?? [], TRUE);
    }
    catch (\DomainException) {
      return FALSE;
    }
  }

  /** Adds a deny filter even when other node access realms grant access. */
  public function filter(SelectInterface $query, string $nodeAlias, AccountInterface $account): void {
    // Match Drupal's explicit, trusted administrative bypass at the entity boundary.
    if ($account->hasPermission('bypass node access')) {
      return;
    }
    $denied = $this->database->select(self::TABLE, 'sync_denied')->fields('sync_denied', ['nid']);
    $allowed = $denied->orConditionGroup();
    foreach ($this->sources() as $source) {
      try {
        $source = $this->source($source['id']);
      }
      catch (\DomainException) {
        continue;
      }
      foreach ($source['policies'] ?? [] as $policy) {
        if ($this->allowed($source['id'], $policy['id'], $account)) {
          $allowed->condition($denied->andConditionGroup()
            ->condition('source', $source['id'])->condition('policy', $policy['id']));
        }
      }
    }
    if (count($allowed)) {
      // SQL has no portable NOT condition-group API; subtract allowed rows instead.
      $readable = $this->database->select(self::TABLE, 'sync_readable')->fields('sync_readable', ['nid']);
      $readable->condition($allowed);
      $denied->condition('nid', $readable, 'NOT IN');
    }
    $query->condition($nodeAlias . '.nid', $denied, 'NOT IN');
  }

  private function sources(): array {
    return $this->configFactory->get('xinshi_knowledge_sync.settings')->get('sources') ?? [];
  }

  private function definition(array $source, string $id): array {
    $matches = array_values(array_filter($source['policies'] ?? [], fn(array $policy) => ($policy['id'] ?? NULL) === $id));
    if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $id) || count($matches) !== 1) {
      throw new \DomainException('unknown_policy');
    }
    return $matches[0];
  }

}
