<?php

declare(strict_types=1);

namespace Drupal\xinshi_office;

/** Server validation of the WordContent v1 protocol, not a DOCX converter. */
final class WordType implements OfficeTypeInterface {

  /** {@inheritdoc} */
  public function kind(): string {
    return 'word';
  }

  /** {@inheritdoc} */
  public function fileFormat(): array {
    return ['extension' => '.docx', 'part' => 'word/document.xml',
      'contentType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
      'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
  }

  /** {@inheritdoc} */
  public function validate(mixed $content): array {
    OfficeInput::object($content, ['version', 'type', 'content']);
    if ($content->version !== 1 || $content->type !== 'doc') {
      throw new OfficeException('invalid_content');
    }
    self::children($content->content, 1, 2000);
    $ids = $assets = [];
    $nodes = $blocks = $characters = $images = 0;
    $walk = static function (mixed $node, array $allowed, int $depth) use (
      &$walk, &$ids, &$assets, &$nodes, &$blocks, &$characters, &$images,
    ): void {
      if (++$nodes > 10000 || $depth > 12) {
        throw new OfficeException('content_limit');
      }
      OfficeInput::object($node, ['type'], ['attrs', 'content', 'text', 'marks']);
      if (!in_array($node->type, $allowed, TRUE)) {
        throw new OfficeException('invalid_content');
      }
      $type = $node->type;
      if ($type === 'text') {
        OfficeInput::object($node, ['type', 'text'], ['marks']);
        OfficeInput::text($node->text, 100000);
        if ($node->text === '') {
          throw new OfficeException('invalid_content');
        }
        $characters += strlen(mb_convert_encoding($node->text, 'UTF-16LE', 'UTF-8')) / 2;
        if ($characters > 100000) {
          throw new OfficeException('content_limit');
        }
        if (property_exists($node, 'marks')) {
          self::children($node->marks, 0, 4);
          $marks = [];
          foreach ($node->marks as $mark) {
            OfficeInput::object($mark, ['type'], ['attrs']);
            if (!in_array($mark->type, ['bold', 'italic', 'underline', 'link'], TRUE) || isset($marks[$mark->type])) {
              throw new OfficeException('invalid_content');
            }
            $marks[$mark->type] = TRUE;
            if ($mark->type === 'link') {
              OfficeInput::object($mark->attrs ?? NULL, ['href']);
              OfficeInput::text($mark->attrs->href, 2048, TRUE);
              if (!preg_match('/^(https?:\/\/[^\s\/]+|mailto:[^\s]+)/iu', $mark->attrs->href) ||
                preg_match('/\s/u', $mark->attrs->href)) {
                throw new OfficeException('invalid_link');
              }
            }
            else {
              OfficeInput::object($mark, ['type']);
            }
          }
        }
        return;
      }
      if ($type === 'hardBreak') {
        OfficeInput::object($node, ['type']);
        return;
      }
      if (++$blocks > 2000) {
        throw new OfficeException('content_limit');
      }
      $keys = match ($type) {
        'heading' => ['id', 'level'], 'orderedList' => ['id', 'start'],
        'image' => ['id', 'assetId', 'width', 'height', 'alt'], default => ['id'],
      };
      OfficeInput::object($node->attrs ?? NULL, $keys);
      $attrs = $node->attrs;
      self::id($attrs->id);
      if (isset($ids[$attrs->id])) {
        throw new OfficeException('invalid_reference');
      }
      $ids[$attrs->id] = TRUE;
      if ($type === 'image') {
        OfficeInput::object($node, ['type', 'attrs']);
        self::id($attrs->assetId);
        OfficeInput::text($attrs->alt, 1000);
        foreach (['width', 'height'] as $dimension) {
          if (!is_int($attrs->$dimension) || $attrs->$dimension < 1 || $attrs->$dimension > 8192) {
            throw new OfficeException('invalid_content');
          }
        }
        if (++$images > 20) {
          throw new OfficeException('content_limit');
        }
        $assets[$attrs->assetId] = TRUE;
        return;
      }
      OfficeInput::object($node, ['type', 'attrs', 'content']);
      self::children($node->content, in_array($type, ['paragraph', 'heading'], TRUE) ? 0 : 1, 10000);
      if (($type === 'heading' && !in_array($attrs->level, [1, 2, 3, 4, 5, 6], TRUE)) ||
        ($type === 'orderedList' && (!is_int($attrs->start) || $attrs->start < 1 || $attrs->start > 9999))) {
        throw new OfficeException('invalid_content');
      }
      $accepted = match ($type) {
        'paragraph', 'heading' => ['text', 'hardBreak'], 'table' => ['tableRow'],
        'tableRow' => ['tableCell', 'tableHeader'], 'tableCell', 'tableHeader' => ['paragraph'],
        'listItem' => ['paragraph', 'bulletList', 'orderedList'], default => ['listItem'],
      };
      if (($type === 'table' && count($node->content) > 200) ||
        ($type === 'tableRow' && count($node->content) > 50)) {
        throw new OfficeException('content_limit');
      }
      if ($type === 'listItem' && ($node->content[0]->type ?? NULL) !== 'paragraph') {
        throw new OfficeException('invalid_content');
      }
      foreach ($node->content as $child) {
        $walk($child, $accepted, $depth + 1);
      }
      if ($type === 'table') {
        $columns = count($node->content[0]->content);
        $body = FALSE;
        foreach ($node->content as $row) {
          $header = $row->content[0]->type === 'tableHeader';
          if (count($row->content) !== $columns || ($header && $body)) {
            throw new OfficeException('invalid_content');
          }
          foreach ($row->content as $cell) {
            if (($cell->type === 'tableHeader') !== $header) {
              throw new OfficeException('invalid_content');
            }
          }
          $body = $body || !$header;
        }
      }
    };
    foreach ($content->content as $block) {
      $walk($block, ['paragraph', 'heading', 'bulletList', 'orderedList', 'table', 'image'], 1);
    }
    return array_keys($assets);
  }

  private static function children(mixed $value, int $minimum, int $maximum): void {
    if (!is_array($value) || !array_is_list($value) || count($value) < $minimum || count($value) > $maximum) {
      throw new OfficeException('invalid_content');
    }
  }

  private static function id(mixed $value): void {
    if (!is_string($value) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $value)) {
      throw new OfficeException('invalid_reference');
    }
  }

}
