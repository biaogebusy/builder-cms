<?php

declare(strict_types=1);

namespace Drupal\layout_builder_st\Install\Requirements;

use Drupal\Core\Extension\InstallRequirementsInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Install time requirements.
 */
class LayoutBuilderSynchronous implements InstallRequirementsInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRequirements(): array {
    $requirements = [];

    if (\Drupal::moduleHandler()->moduleExists('layout_builder_at')) {
      $requirements['layout_builder_at_incompatibility'] = [
        'severity' => RequirementSeverity::Error,
        'description' => new TranslatableMarkup('Layout Builder Symmetric Translations can not be installed when Layout Builder Asymmetric Translations is also installed.'),
      ];
    }

    return $requirements;
  }

}
