<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_ai\Service\HarnessSettings;
use Symfony\Component\HttpFoundation\JsonResponse;

/** Exposes only non-secret Harness settings to authenticated chat requests. */
final class HarnessSettingsController extends ControllerBase {

  public function read(): JsonResponse {
    return new JsonResponse([
      'version' => 1,
      ...HarnessSettings::normalize($this->config('xinshi_ai.settings')->get('harness')),
    ], 200, ['Cache-Control' => 'private, no-store']);
  }

}
