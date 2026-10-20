<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_reference;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/** Selects one site-wide protocol; cutover is an explicit operations step. */
final class ChatStorage {

  public function __construct(private readonly ConfigFactoryInterface $config) {}

  public function version(): int {
    // Existing installations have no settings until their separately verified cutover.
    $version = $this->config->get('xinshi_ai_reference.settings')->get('storage_version') ?? 1;
    if (!in_array($version, [1, 2, 3], TRUE)) {
      throw new ServiceUnavailableHttpException(NULL, 'Invalid chat storage configuration.');
    }
    return $version;
  }

}
