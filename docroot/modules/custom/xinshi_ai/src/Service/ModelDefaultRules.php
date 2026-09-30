<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

/** Shared default-model validation for the management API and admin form. */
final class ModelDefaultRules {

  /** Return an error for an invalid selection; null or an empty ID clears it. */
  public static function validate(ModelRegistryServiceInterface $registry, string $mode, mixed $id): ?string {
    if (!in_array($mode, ModelRegistryServiceInterface::DEFAULT_MODES, TRUE)) {
      return sprintf('mode must be one of: %s.', implode(', ', ModelRegistryServiceInterface::DEFAULT_MODES));
    }
    if ($id === NULL || $id === '') {
      return NULL;
    }
    if (!is_string($id)) {
      return 'model id must be a string or null.';
    }
    $auxiliary = in_array($mode, ['critic', 'classifier'], TRUE);
    $capability = $auxiliary ? 'chat' : $mode;
    $model = $registry->getModel($id);
    if ($model === NULL || !in_array($capability, $model['capabilities'] ?? [], TRUE)) {
      return sprintf("Model '%s' must be an enabled model with the '%s' capability.", $id, $capability);
    }
    if ($auxiliary && ($model['platform'] ?? '') !== 'xinshi') {
      return sprintf('The default %s must use the xinshi platform.', $mode);
    }
    return NULL;
  }

}
