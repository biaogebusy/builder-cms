<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Builds image_job node doubles whose fields can be read and written like the real ones.
 */
trait ImageJobNodeTrait {

  /**
   * @param array<string,mixed> $fields
   *   Field name to scalar value; `field_status` defaults to `queued`.
   */
  private function jobNode(string $uuid, array $fields = []): NodeInterface&MockObject {
    $values = $fields + ['field_status' => 'queued', 'field_job_kind' => 'text_to_image',
      'field_n_succeeded' => 0, 'field_n_requested' => 4, 'field_completed_at' => NULL,
      'field_status_reason' => NULL, 'field_error_code' => NULL, 'field_platform' => 'xinshi',
      'field_model' => 'qwen-image', 'field_prompt' => 'a cat', 'field_task_id' => NULL,
      'field_params' => '{}'];
    $node = $this->createMock(NodeInterface::class);
    $node->method('uuid')->willReturn($uuid);
    $node->method('id')->willReturn(1);
    $node->method('getOwnerId')->willReturn(7);
    $node->method('label')->willReturn('a cat');
    $lists = [];
    $node->method('get')->willReturnCallback(function (string $field) use (&$values, &$lists): FieldItemListInterface {
      if (!isset($lists[$field])) {
        $list = $this->createMock(FieldItemListInterface::class);
        // Closures, not arrow functions: the values must be read at call time,
        // after later set() calls, not copied when the list double is built.
        $list->method('__get')->willReturnCallback(function (string $property) use (&$values, $field): mixed {
          return $property === 'value' ? ($values[$field] ?? NULL) : NULL;
        });
        // `->value ?? $default` asks __isset() first and never reaches __get()
        // without it, so the double would read every field as its default.
        $list->method('__isset')->willReturnCallback(function (string $property) use (&$values, $field): bool {
          return $property === 'value' && isset($values[$field]);
        });
        $list->method('getValue')->willReturnCallback(function () use (&$values, $field): array {
          return isset($values[$field]) ? [['value' => $values[$field]]] : [];
        });
        $lists[$field] = $list;
      }
      return $lists[$field];
    });
    $node->method('set')->willReturnCallback(function (string $field, mixed $value) use (&$values, $node): NodeInterface {
      $values[$field] = is_array($value) ? ($value[0]['value'] ?? NULL) : $value;
      return $node;
    });
    return $node;
  }

}
