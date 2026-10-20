<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_reference;

use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\node\NodeInterface;

/** Projects actual Drupal field values into the strict current storage contract. */
final class ChatSessionSnapshot {

  public const NEW_FIELDS = ['storage_version', 'edit_version', 'storage_mode', 'content_format',
    'run_id', 'run_created_at_ms', 'is_error', 'output_completion', 'ui_review',
    'approval_timeline', 'knowledge_refs', 'result_reference'];
  public const PLAN_FIELDS = ['plan_context'];
  private const TEXT_FIELDS = ['session_role', 'content_format', 'reasoning_content',
    'summary', 'img', 'style_preset', 'run_id', 'storage_mode', 'output_completion'];

  public function __construct(private readonly ResourceTypeRepositoryInterface $resources) {}

  /** Empty installed fields leave legacy sessions untouched; any use requires a complete DTO. */
  public function usesNewFields(NodeInterface $node): bool {
    foreach ([...self::NEW_FIELDS, ...self::PLAN_FIELDS] as $public) {
      $name = $this->field($node, $public);
      if ($node->hasField($name) && !$node->get($name)->isEmpty()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /** The codec rejects missing versions, mixed shapes and presentation copies. */
  public function validate(NodeInterface $node, array $conversation_ids = []): \stdClass {
    $allowed = array_map(fn($name) => $this->field($node, $name),
      [...self::NEW_FIELDS, ...self::PLAN_FIELDS, ...self::TEXT_FIELDS,
        'content', 'total_tokens', 'uiparsing']);
    foreach ($node->getFieldDefinitions() as $name => $definition) {
      if (!$definition->isComputed() && !$definition->getFieldStorageDefinition()->isBaseField() &&
        !in_array($name, $allowed, TRUE) && !$node->get($name)->isEmpty()) {
        $this->reject();
      }
    }
    $data = new \stdClass();
    $data->title = $node->label();
    foreach (self::TEXT_FIELDS as $name) {
      $data->$name = $this->scalar($node, $name,
        $name === 'reasoning_content' ? 'string_long' : 'string');
      if ($data->$name !== NULL && !is_string($data->$name)) {
        $this->reject();
      }
    }
    foreach (['storage_version', 'edit_version', 'total_tokens', 'run_created_at_ms'] as $name) {
      $data->$name = $this->integer($node, $name);
    }
    foreach (['uiparsing', 'is_error'] as $name) {
      $value = $this->scalar($node, $name, 'boolean');
      if (!in_array($value, [NULL, TRUE, FALSE, 0, 1, '0', '1'], TRUE)) {
        $this->reject();
      }
      $data->$name = $value === NULL ? NULL : in_array($value, [TRUE, 1, '1'], TRUE);
    }
    $content = $this->items($node, 'content', 'text_long');
    if (count($content) > 1) {
      $this->reject();
    }
    $data->content = $content === [] ? NULL : (object) [
      'value' => $content[0]['value'] ?? NULL, 'format' => $content[0]['format'] ?? NULL,
    ];
    if (self::managedEnvelope($data->content?->value)) {
      $this->reject();
    }
    foreach (['ui_review', 'approval_timeline', 'result_reference'] as $name) {
      $json = $this->scalar($node, $name, 'string_long');
      $data->$name = $json === NULL ? NULL :
        (is_string($json) ? ChatSessionFormat::decode($json) : $this->reject());
    }
    if ($data->storage_version === 3) {
      $json = $this->scalar($node, 'plan_context', 'string_long');
      $data->plan_context = $json === NULL ? NULL :
        (is_string($json) ? ChatSessionFormat::decode($json) : $this->reject());
    }
    else {
      $name = $this->field($node, 'plan_context');
      if ($node->hasField($name) && $node->get($name)->getValue() !== []) {
        $this->reject();
      }
    }
    $data->knowledge_refs = array_map(static fn(array $item) => $item['value'] ?? NULL,
      $this->items($node, 'knowledge_refs', 'string'));
    return ChatSessionFormat::validate($data, $node->uuid(), $conversation_ids);
  }

  /** Resolve the existing JSON:API public name without enabling or rewriting fields. */
  public function field(NodeInterface $node, string $public): string {
    return $this->resources->get('node', $node->bundle())?->getInternalName($public) ?? $public;
  }

  /** Recognize managed outer wrappers without interpreting ordinary inner Plan text. */
  public static function managedEnvelope(mixed $content,
    array $kinds = ['xinshi-chat-run', 'xinshi-chat-user', 'xinshi-protected-run']): bool {
    if (!is_string($content) || !str_starts_with(ltrim($content), '{')) {
      return FALSE;
    }
    // Inspect top-level pairs before last-wins decoding, including escaped and duplicate keys.
    $depth = 0;
    $previous = $before_previous = '';
    for ($offset = 0, $length = strlen($content); $offset < $length;) {
      $token = $content[$offset++];
      if (str_contains(" \t\r\n", $token)) {
        continue;
      }
      if ($token === '"') {
        $start = $offset - 1;
        while ($offset < $length) {
          $char = $content[$offset++];
          if ($char === '\\') {
            $offset++;
          }
          elseif ($char === '"') {
            break;
          }
        }
        $token = substr($content, $start, $offset - $start);
        if ($depth === 1 && $previous === ':' &&
          json_decode($before_previous) === 'kind' && in_array(json_decode($token), $kinds, TRUE)) {
          return TRUE;
        }
      }
      elseif ($token === '{' || $token === '[') {
        $depth++;
      }
      elseif ($token === '}' || $token === ']') {
        $depth--;
      }
      $before_previous = $previous;
      $previous = $token;
    }
    return FALSE;
  }

  private function items(NodeInterface $node, string $public, ?string $type = NULL): array {
    $name = $this->field($node, $public);
    if (!$node->hasField($name) ||
      ($type !== NULL && $node->get($name)->getFieldDefinition()->getType() !== $type)) {
      $this->reject();
    }
    return $node->get($name)->getValue();
  }

  private function scalar(NodeInterface $node, string $public, ?string $type = NULL): mixed {
    $items = $this->items($node, $public, $type);
    if (count($items) > 1) {
      $this->reject();
    }
    return $items[0]['value'] ?? NULL;
  }

  private function integer(NodeInterface $node, string $public): ?int {
    $value = $this->scalar($node, $public, 'integer');
    if ($value === NULL || is_int($value)) {
      return $value;
    }
    // SQL field plugins return decimal strings; arbitrary client coercions remain invalid.
    if (!is_string($value) || !preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $value) ||
      filter_var($value, FILTER_VALIDATE_INT) === FALSE) {
      $this->reject();
    }
    return (int) $value;
  }

  private function reject(): never {
    throw new ChatSessionFormatException('invalid_shape');
  }

}
