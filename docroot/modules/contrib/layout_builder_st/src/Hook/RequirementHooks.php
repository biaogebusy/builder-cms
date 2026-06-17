<?php

namespace Drupal\layout_builder_st\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Requirement hooks.
 */
class RequirementHooks {

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  use StringTranslationTrait;

  /**
   * Implements hook_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements($phase) {
    $requirements = [];

    if ($this->moduleHandler->moduleExists('layout_builder_at')) {
      $requirements['layout_builder_at_incompatibility'] = [
        'severity' => RequirementSeverity::Error,
        'description' => $this->t('Layout Builder Symmetric Translations is not compatible with Layout Builder Asymmetric Translations. One of these should be uninstalled'),
      ];
    }

    return $requirements;
  }

}
