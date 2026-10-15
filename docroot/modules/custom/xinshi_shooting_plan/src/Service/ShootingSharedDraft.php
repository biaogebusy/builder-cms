<?php

declare(strict_types=1);

namespace Drupal\xinshi_shooting_plan\Service;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Validates the editable plan shape without accepting task metadata or arbitrary extra fields. */
final class ShootingSharedDraft {

  /** Rejects invalid identities, references, field sets and editable value bounds. */
  public static function validate(mixed $plan): void {
    self::keys($plan, ['title', 'concept', 'audience', 'structure', 'assumptions', 'preparation',
      'styleGuide', 'aspectRatio', 'targetDuration', 'shots']);
    self::check(mb_strlen(json_encode($plan, JSON_UNESCAPED_UNICODE)) <= 200000);
    foreach (['title', 'concept', 'audience', 'styleGuide'] as $key) {
      self::text($plan[$key]);
    }
    self::strings($plan['assumptions']);
    self::strings($plan['preparation']);
    self::check(in_array($plan['aspectRatio'], ['9:16', '16:9', '1:1'], TRUE) &&
      in_array($plan['targetDuration'], [30, 60, 90, 180], TRUE));
    self::items($plan['structure'], 1, 12);
    $sections = [];
    foreach ($plan['structure'] as $section) {
      self::keys($section, ['id', 'title']);
      self::id($section['id'], $sections);
      self::text($section['title']);
    }
    self::items($plan['shots'], 2, 24);
    $shots = [];
    foreach ($plan['shots'] as $shot) {
      self::keys($shot, ['id', 'sectionId', 'duration', 'framing', 'visual', 'camera', 'lighting',
        'audio', 'actions', 'acceptance', 'dialogue', 'sketchPrompt', 'tip', 'sounds']);
      self::id($shot['id'], $shots);
      self::check(is_string($shot['sectionId']) && isset($sections[$shot['sectionId']]) &&
        is_int($shot['duration']) && $shot['duration'] > 0 && $shot['duration'] <= 180 &&
        in_array($shot['framing'], ['wide', 'medium', 'close', 'detail'], TRUE));
      foreach (['visual', 'camera', 'lighting', 'audio', 'acceptance', 'sketchPrompt', 'tip'] as $key) {
        self::text($shot[$key]);
      }
      self::strings($shot['actions']);
      self::strings($shot['sounds'], 0);
      self::items($shot['dialogue'], 0, 12);
      foreach ($shot['dialogue'] as $line) {
        self::keys($line, ['speaker', 'text']);
        self::text($line['speaker']);
        self::text($line['text']);
      }
    }
  }

  private static function check(bool $valid): void {
    if (!$valid) {
      throw new UnprocessableEntityHttpException('shooting_draft_invalid');
    }
  }

  private static function keys(mixed $value, array $keys): void {
    self::check(is_array($value) && count($value) === count($keys) && !array_diff($keys, array_keys($value)));
  }

  private static function text(mixed $value): void {
    self::check(is_string($value) && trim($value) !== '' && mb_strlen($value) <= 4000);
  }

  private static function items(mixed $value, int $min, int $max): void {
    self::check(is_array($value) && array_is_list($value) && count($value) >= $min && count($value) <= $max);
  }

  private static function strings(mixed $value, int $min = 1): void {
    self::items($value, $min, 12);
    foreach ($value as $item) {
      self::text($item);
    }
  }

  private static function id(mixed $value, array &$seen): void {
    self::check(is_string($value) && (bool) preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $value) && !isset($seen[$value]));
    $seen[$value] = TRUE;
  }

}
