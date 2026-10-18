<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_reference;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Reads bounded message bodies in relationship delta order, failing closed on gaps. */
final class ChatHistoryReader {

  public const PAGE_SIZE = 50;
  private const LEGACY_FIELDS = ['title', 'session_role', 'content', 'reasoning_content',
    'summary', 'total_tokens', 'img', 'uiparsing', 'style_preset'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountInterface $account,
    private readonly ResourceTypeRepositoryInterface $resources,
    private readonly ChatSessionSnapshot $snapshots,
    private readonly ChatStorage $protocol,
    private readonly PrivateKey $privateKey,
  ) {}

  /** Every page rechecks authorization and binds its cursor to this exact relation revision. */
  public function page(string $chat_uuid, ?string $cursor): array {
    $chat = $this->conversation($chat_uuid);
    $version = $this->protocol->version();
    $ids = $this->members($chat);
    $stamp = hash('sha256', json_encode([$chat_uuid, $chat->getRevisionId(),
      $chat->language()->getId(), $this->account->id(), $version, $ids], JSON_THROW_ON_ERROR));
    $offset = 0;
    if ($cursor !== NULL) {
      $parts = explode('.', $cursor);
      if (count($parts) !== 2 || strlen($cursor) > 512 ||
        !hash_equals($this->signature($parts[0]), $parts[1])) {
        throw new BadRequestHttpException('Invalid history cursor.');
      }
      $value = json_decode(base64_decode($parts[0], TRUE) ?: '', TRUE);
      if (!is_array($value) || !is_int($value['offset'] ?? NULL) || $value['offset'] < 1 ||
        !is_string($value['stamp'] ?? NULL)) {
        throw new BadRequestHttpException('Invalid history cursor.');
      }
      if ($value['stamp'] !== $stamp || $value['offset'] >= count($ids)) {
        throw new ConflictHttpException('Chat history changed; restart the read.');
      }
      $offset = $value['offset'];
    }
    $page_ids = array_slice($ids, $offset, self::PAGE_SIZE);
    $storage = $this->entities->getStorage('node');
    $storage->resetCache($page_ids);
    $nodes = $storage->loadMultiple($page_ids);
    $messages = [];
    foreach ($page_ids as $id) {
      $node = $nodes[$id] ?? NULL;
      if (!$node instanceof NodeInterface || $node->bundle() !== 'ai_session') {
        throw new ConflictHttpException('Chat history contains an unavailable message.');
      }
      $messages[] = ['id' => $node->uuid(), 'created' => (int) $node->getCreatedTime(),
        'fields' => $this->fields($node, $chat_uuid, $version)];
    }
    $next = $offset + count($messages);
    $payload = base64_encode(json_encode(['offset' => $next, 'stamp' => $stamp], JSON_THROW_ON_ERROR));
    return ['storage_version' => $version, 'total' => count($ids), 'offset' => $offset,
      'messages' => $messages, 'next' => $next < count($ids) ? $payload . '.' . $this->signature($payload) : NULL];
  }

  /** Fetches a current field snapshot before an edit, including after browser cache recovery. */
  public function session(string $chat_uuid, string $uuid): \stdClass {
    $chat = $this->conversation($chat_uuid);
    $node = $this->load($uuid, 'ai_session');
    if (!in_array((string) $node->id(), $this->members($chat), TRUE)) {
      throw new AccessDeniedHttpException();
    }
    $version = $this->protocol->version();
    if ($version === 1) {
      throw new ConflictHttpException('The site has not selected split-field chat storage.');
    }
    return $this->fields($node, $chat_uuid, $version);
  }

  private function fields(NodeInterface $node, string $chat_uuid, int $version): \stdClass {
    $this->owned($node);
    $this->field($node, 'created');
    if ($version !== 1) {
      try {
        $fields = $this->snapshots->validate($node, [$chat_uuid]);
      }
      catch (ChatSessionFormatException) {
        throw new UnprocessableEntityHttpException('Invalid chat history fields.');
      }
      if ($fields->storage_version !== $version) {
        throw new ConflictHttpException('Chat history does not match the site storage version.');
      }
      if ($fields->storage_mode === 'protected_reference' &&
        (string) $node->getOwnerId() !== (string) $this->account->id()) {
        throw new AccessDeniedHttpException();
      }
      foreach (array_keys(get_object_vars($fields)) as $public) {
        $this->field($node, $public);
      }
      return $fields;
    }
    if ($this->snapshots->usesNewFields($node)) {
      throw new ConflictHttpException('Chat history does not match the site storage version.');
    }
    $fields = new \stdClass();
    foreach (self::LEGACY_FIELDS as $public) {
      $name = $this->snapshots->field($node, $public);
      if (!$node->hasField($name)) {
        $fields->$public = NULL;
        continue;
      }
      $items = $node->get($this->field($node, $public));
      $fields->$public = $items->value;
    }
    if (ChatSessionSnapshot::managedEnvelope($fields->content, ['xinshi-protected-run']) &&
      (string) $node->getOwnerId() !== (string) $this->account->id()) {
      throw new AccessDeniedHttpException();
    }
    return $fields;
  }

  private function conversation(string $uuid): NodeInterface {
    $node = $this->load($uuid, 'conversation');
    $this->owned($node);
    return $node;
  }

  private function members(NodeInterface $chat): array {
    $items = $chat->get($this->field($chat, 'sessions'))->getValue();
    $ids = array_map('strval', array_column($items, 'target_id'));
    if (count($ids) !== count($items) || count(array_unique($ids)) !== count($ids)) {
      throw new ConflictHttpException('Chat history contains invalid membership.');
    }
    return $ids;
  }

  private function load(string $uuid, string $bundle): NodeInterface {
    if (!Uuid::isValid($uuid)) {
      throw new BadRequestHttpException('Invalid chat identity.');
    }
    $nodes = $this->entities->getStorage('node')->loadByProperties(['uuid' => $uuid, 'type' => $bundle]);
    $node = reset($nodes);
    if (!$node instanceof NodeInterface) {
      throw new NotFoundHttpException();
    }
    return $node;
  }

  private function owned(NodeInterface $node): void {
    if (!$this->account->isAuthenticated() ||
      ((string) $node->getOwnerId() !== (string) $this->account->id() &&
        !$this->account->hasPermission('bypass node access')) ||
      !$node->access('view', $this->account)) {
      throw new AccessDeniedHttpException();
    }
  }

  private function field(NodeInterface $node, string $public): string {
    $resource = $this->resources->get('node', $node->bundle());
    $name = $resource?->getInternalName($public);
    if (!$name || !$resource->isFieldEnabled($name) || !$node->hasField($name) ||
      !$node->get($name)->access('view', $this->account)) {
      throw new AccessDeniedHttpException();
    }
    return $name;
  }

  private function signature(string $payload): string {
    return hash_hmac('sha256', 'chat-history-v1:' . $payload, $this->privateKey->get());
  }

}
