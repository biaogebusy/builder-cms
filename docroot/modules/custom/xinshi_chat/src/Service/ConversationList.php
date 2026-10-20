<?php

declare(strict_types=1);

namespace Drupal\xinshi_chat\Service;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/** Lists the current account's conversations without relying on site-created Views. */
final class ConversationList {

  public const PAGE_SIZE = 20;
  private const SCAN_LIMIT = 10000;
  private const FIELDS = ['uuid', 'title', 'model', 'platform', 'chat_type', 'mode', 'created', 'uid'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountInterface $account,
    private readonly ResourceTypeRepositoryInterface $resources,
    private readonly DateFormatterInterface $dates,
  ) {}

  /** Count and filter only rows whose returned fields are readable by this principal. */
  public function page(string $page, string $keyword, string $chat_type): array {
    return $this->listing('conversation', self::FIELDS, $page, $keyword, $chat_type);
  }

  /** Preserve the older message-list projection under the same author access boundary. */
  public function sessions(string $page, string $keyword): array {
    $result = $this->listing('ai_session', ['uuid', 'title', 'summary', 'uid', 'created',
      'session_role', 'total_tokens'], $page, $keyword, '');
    $result['rows'] = array_map(static fn(array $row) => [
      'uuid' => $row['uuid'], 'title' => $row['title'], 'summary' => $row['summary'],
      'user' => $row['uid'], 'date' => substr($row['created'], 0, 10),
      'time' => substr($row['created'], 11), 'role' => $row['session_role'],
      'total_tokens' => $row['total_tokens'],
    ], $result['rows']);
    return $result;
  }

  private function listing(string $bundle, array $fields, string $page, string $keyword, string $chat_type): array {
    if (!$this->account->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }
    if (!preg_match('/^(0|[1-9][0-9]{0,5})$/D', $page) ||
      mb_strlen($keyword) > 255 || mb_strlen($chat_type) > 255) {
      throw new BadRequestHttpException('Invalid conversation list filters.');
    }
    $storage = $this->entities->getStorage('node');
    $resource = $this->resources->get('node', $bundle);
    if (!$resource || $resource->isInternal()) {
      throw new AccessDeniedHttpException();
    }
    $rows = [];
    $total = 0;
    $start = (int) $page * self::PAGE_SIZE;
    // Entity/field grants may vary per row. Apply filters after access checks, not in SQL.
    for ($offset = 0; $offset <= self::SCAN_LIMIT; $offset += 100) {
      $ids = $storage->getQuery()->accessCheck(TRUE)->condition('type', $bundle)
        ->condition('uid', $this->account->id())->sort('created', 'DESC')->sort('nid', 'DESC')
        ->range($offset, 100)->execute();
      if (!$ids) {
        break;
      }
      if ($offset === self::SCAN_LIMIT) {
        throw new ServiceUnavailableHttpException(NULL, 'Conversation list scan limit exceeded.');
      }
      foreach ($storage->loadMultiple($ids) as $node) {
        if (!$node->access('view', $this->account)) {
          continue;
        }
        $row = [];
        foreach ($fields as $name) {
          // JSON:API emits the UUID as resource id and disables it as an attribute.
          if (($name !== 'uuid' && !$resource->isFieldEnabled($name)) ||
            !$node->get($name)->access('view', $this->account)) {
            continue 2;
          }
          $row[$name] = $name === 'uid' ? (string) $node->getOwnerId() : $node->get($name)->value;
        }
        if (($keyword !== '' && mb_stripos($row['title'], $keyword) === FALSE) ||
          ($chat_type !== '' && $row['chat_type'] !== $chat_type)) {
          continue;
        }
        if ($total >= $start && count($rows) < self::PAGE_SIZE) {
          $row['created'] = $this->dates->format((int) $row['created'], 'custom', 'Y/m/d H:i:s');
          $row['path'] = '';
          $rows[] = $row;
        }
        $total++;
      }
      $storage->resetCache($ids);
    }
    return ['rows' => $rows, 'pager' => ['current_page' => (int) $page,
      'total_pages' => (int) ceil($total / self::PAGE_SIZE), 'total_items' => $total,
      'items_per_page' => self::PAGE_SIZE]];
  }

}
