<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/** Resolves current labels for one bounded log page without reading message bodies. */
final class AiLogMetadata {

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly ResourceTypeRepositoryInterface $resources,
    private readonly AccountInterface $account,
  ) {}

  /** Entity and field access remain authoritative even for an AI administrator. */
  public function read(array $query): array {
    if (!$this->account->isAuthenticated() || !$this->account->hasPermission('administer xinshi_ai')) {
      throw new AccessDeniedHttpException();
    }
    if (array_diff(array_keys($query), ['chatIds', 'userIds'])) {
      throw new BadRequestHttpException('Unknown metadata query.');
    }
    $chatIds = $this->ids($query['chatIds'] ?? '', '/^[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}$/Di');
    $userIds = $this->ids($query['userIds'] ?? '', '/^(?:0|[1-9][0-9]{0,9})$/D');
    if (array_filter($userIds, static fn(string $id): bool => (int) $id > 2147483647)) {
      throw new BadRequestHttpException('Invalid user identifier.');
    }
    $conversations = [];
    $resource = $this->resources->get('node', 'conversation');
    if ($chatIds && $resource && !$resource->isInternal() && $resource->isFieldEnabled('title')) {
      $storage = $this->entities->getStorage('node');
      $ids = $storage->getQuery()->accessCheck(TRUE)->condition('type', 'conversation')
        ->condition('uuid', $chatIds, 'IN')->range(0, 100)->execute();
      foreach ($storage->loadMultiple($ids) as $node) {
        if (!$node->access('view', $this->account) || !$node->get('title')->access('view', $this->account)) {
          continue;
        }
        $row = ['id' => $node->uuid(), 'title' => $node->label()];
        $typeField = $resource->getInternalName('chat_type');
        if ($typeField && $resource->isFieldEnabled($typeField) && $node->hasField($typeField)
          && $node->get($typeField)->access('view', $this->account)) {
          $row['type'] = $node->get($typeField)->value;
        }
        $conversations[] = $row;
      }
    }
    $users = [];
    if ($userIds) {
      foreach ($this->entities->getStorage('user')->loadMultiple($userIds) as $user) {
        if ($user->access('view label', $this->account)) {
          $users[] = ['uid' => (string) $user->id(), 'name' => $user->getDisplayName()];
        }
      }
    }
    return ['conversations' => $conversations, 'users' => $users];
  }

  private function ids(mixed $value, string $pattern): array {
    if (!is_string($value) || strlen($value) > 3700) {
      throw new BadRequestHttpException('Invalid metadata identifiers.');
    }
    if ($value === '') {
      return [];
    }
    $ids = explode(',', $value);
    if (count($ids) > 100) {
      throw new BadRequestHttpException('At most 100 identifiers are allowed.');
    }
    foreach ($ids as $id) {
      if (!preg_match($pattern, $id)) {
        throw new BadRequestHttpException('Invalid metadata identifier.');
      }
    }
    return array_values(array_unique($ids));
  }

}
