<?php

declare(strict_types=1);

namespace Drupal\xinshi_shooting_plan\Service;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Resolves application documents under current node, field and effective-account access. */
final class ShootingDocumentAccess {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly AccountProxyInterface $account,
    private readonly ResourceTypeRepositoryInterface $resources,
  ) {}

  /**
   * Loads the linked working copy, taking the session writer's lock order for writes.
   *
   * The caller owns the transaction when supplying an expected content hash.
   */
  public function read(string $chat_uuid, string $session_uuid, ?string $expected_hash = NULL): array {
    if (!Uuid::isValid($chat_uuid) || !Uuid::isValid($session_uuid)) {
      throw new NotFoundHttpException();
    }
    if (!$this->account->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }
    $write = $expected_hash !== NULL;
    $chat = $this->load($chat_uuid, 'conversation', $write);
    $session = $this->load($session_uuid, 'ai_session', $write);
    foreach ([$chat, $session] as $node) {
      if ((string) $node->getOwnerId() !== (string) $this->account->id() ||
        !$node->access('view', $this->account) || ($write && !$node->access('update', $this->account))) {
        throw new AccessDeniedHttpException();
      }
    }
    $sessions = $this->field($chat, 'sessions', FALSE);
    $type = $this->field($chat, 'chat_type', FALSE);
    $content_field = $this->field($session, 'content', $write);
    if ($chat->get($type)->value !== 'shooting-plan' ||
      !in_array((string) $session->id(), array_map('strval', array_column(
        $chat->get($sessions)->getValue(), 'target_id')), TRUE)) {
      throw new AccessDeniedHttpException();
    }
    $content = (string) $session->get($content_field)->value;
    if ($write && (!preg_match('/^[a-f0-9]{64}$/D', $expected_hash) ||
      !hash_equals(hash('sha256', $content), $expected_hash))) {
      throw new ConflictHttpException('shooting_document_changed');
    }
    if (strlen($content) > 2 * 1024 * 1024) {
      throw new UnprocessableEntityHttpException('shooting_document_invalid');
    }
    $doc = json_decode($content, TRUE);
    if (!is_array($doc) || ($doc['kind'] ?? NULL) !== 'xinshi-shooting-plan' ||
      ($doc['version'] ?? NULL) !== 1 || ($doc['id'] ?? NULL) !== $chat_uuid ||
      ($doc['sessionId'] ?? NULL) !== $session_uuid || ($doc['status'] ?? NULL) !== 'ready') {
      throw new UnprocessableEntityHttpException('shooting_document_invalid');
    }
    self::prompts($doc['draft'] ?? NULL);
    return $doc;
  }

  /** Validates only the bounded visual projection consumed by the image task. */
  public static function prompts(mixed $plan): array {
    if (!is_array($plan) || !in_array($plan['aspectRatio'] ?? NULL, ['9:16', '16:9', '1:1'], TRUE) ||
      !self::text($plan['styleGuide'] ?? NULL) || !is_array($plan['shots'] ?? NULL) ||
      !array_is_list($plan['shots']) || count($plan['shots']) < 2 || count($plan['shots']) > 24) {
      throw new UnprocessableEntityHttpException('shooting_visuals_invalid');
    }
    $prompts = [];
    foreach ($plan['shots'] as $shot) {
      if (!is_array($shot) || !is_string($shot['id'] ?? NULL) ||
        !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $shot['id']) || isset($prompts[$shot['id']]) ||
        !in_array($shot['framing'] ?? NULL, ['wide', 'medium', 'close', 'detail'], TRUE) ||
        !self::text($shot['visual'] ?? NULL) || !self::text($shot['sketchPrompt'] ?? NULL)) {
        throw new UnprocessableEntityHttpException('shooting_visuals_invalid');
      }
      $prompts[$shot['id']] = "Storyboard sketch, {$plan['aspectRatio']}, black and white pencil line art, light gray " .
        "shading. No lettering, subtitles or watermark.\n{$plan['styleGuide']}\n" .
        "Framing: {$shot['framing']}\nScene: {$shot['visual']}\nComposition: {$shot['sketchPrompt']}";
    }
    return $prompts;
  }

  private static function text(mixed $value): bool {
    return is_string($value) && trim($value) !== '' && mb_strlen($value) <= 4000;
  }

  private function load(string $uuid, string $bundle, bool $lock): NodeInterface {
    $query = $this->database->select('node', 'n')->fields('n', ['nid'])
      ->condition('uuid', $uuid)->condition('type', $bundle);
    if ($lock) {
      $query->forUpdate();
    }
    $nid = $query->execute()->fetchField();
    if (!$nid) {
      throw new NotFoundHttpException();
    }
    $storage = $this->entities->getStorage('node');
    $storage->resetCache([$nid]);
    $node = $storage->load($nid);
    if (!$node instanceof NodeInterface || $node->bundle() !== $bundle) {
      throw new NotFoundHttpException();
    }
    return $node;
  }

  private function field(NodeInterface $node, string $public, bool $write): string {
    $resource = $this->resources->get('node', $node->bundle());
    $field = $resource?->getInternalName($public);
    if (!$field || !$resource->isFieldEnabled($field) || !$node->hasField($field) ||
      !$node->get($field)->access('view', $this->account) ||
      ($write && !$node->get($field)->access('edit', $this->account))) {
      throw new AccessDeniedHttpException();
    }
    return $field;
  }

}
