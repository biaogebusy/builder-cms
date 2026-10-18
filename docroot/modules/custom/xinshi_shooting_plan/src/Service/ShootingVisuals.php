<?php

declare(strict_types=1);

namespace Drupal\xinshi_shooting_plan\Service;

use Drupal\Component\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Bounded visual settings and deterministic image inputs, separate from generated text. */
final class ShootingVisuals {

  public const STYLES = [
    'pencil' => 'black and white pencil line art, light gray shading',
    'color' => 'colored storyboard illustration, clean lines and restrained shading',
    'realistic' => 'photorealistic film still, natural materials and realistic lighting',
  ];

  public static function validate(mixed $value): void {
    if (!is_array($value) || array_diff(array_keys($value), ['reference', 'anchorShotId', 'confirmedJobId']) ||
      (array_key_exists('anchorShotId', $value) && !self::shot($value['anchorShotId'])) ||
      (array_key_exists('confirmedJobId', $value) && (!is_string($value['confirmedJobId']) || !Uuid::isValid($value['confirmedJobId'])))) {
      throw new UnprocessableEntityHttpException('shooting_visuals_invalid');
    }
    if (array_key_exists('reference', $value)) {
      $ref = $value['reference'];
      if (!is_array($ref) || array_diff(array_keys($ref), ['mediaId', 'fileId', 'name']) ||
        !is_string($ref['mediaId'] ?? NULL) || !Uuid::isValid($ref['mediaId']) ||
        !is_string($ref['fileId'] ?? NULL) || !Uuid::isValid($ref['fileId']) ||
        !is_string($ref['name'] ?? NULL) || $ref['name'] === '' || mb_strlen($ref['name']) > 255) {
        throw new UnprocessableEntityHttpException('shooting_reference_invalid');
      }
    }
  }

  /** One plan-wide reference, applied to every shot. */
  public static function references(array $visuals): array {
    return isset($visuals['reference']) ? [$visuals['reference']] : [];
  }

  public static function anchor(array $doc): string {
    return $doc['visuals']['anchorShotId'] ?? $doc['draft']['shots'][0]['id'];
  }

  public static function key(string $prompt, array $doc, string $shot): string {
    $ref = $doc['visuals']['reference'] ?? NULL;
    return json_encode([$prompt,
      $ref ? [$ref['mediaId'], $ref['fileId']] : NULL,
      $shot === self::anchor($doc) ? '' : ($doc['visuals']['confirmedJobId'] ?? ''),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR);
  }

  private static function shot(mixed $value): bool {
    return is_string($value) && preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $value);
  }

}
