<?php

namespace Drupal\commerce;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Defines a class for render callbacks.
 */
final class RenderCallbacks implements TrustedCallbackInterface {

  /**
   * Render callback.
   */
  public static function removeTabAttributes(array $element): array {
    unset($element['tab']['#attributes']);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['removeTabAttributes'];
  }

}
