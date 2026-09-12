<?php

declare(strict_types=1);

namespace DrupalCodeGenerator\Command\PhpStormMeta;

use DrupalCodeGenerator\Asset\File;
use DrupalCodeGenerator\Helper\Drupal\HookInfo;
use DrupalCodeGenerator\Helper\Drupal\ModuleInfo;

/**
 * Generates PhpStorm meta-data for Drupal hooks.
 */
final class Hooks {

  /**
   * Constructs the object.
   */
  public function __construct(
    private readonly HookInfo $hookInfo,
    private readonly ModuleInfo $moduleInfo,
  ) {}

  /**
   * Generator callback.
   */
  public function __invoke(): File {
    $hooks = \array_keys($this->hookInfo->getHookTemplates());
    $modules = \array_keys($this->moduleInfo->getExtensions());
    return File::create('.phpstorm.meta.php/hooks.php')
      ->template('hooks.php.twig')
      ->vars(['hooks' => $hooks, 'modules' => $modules]);
  }

}
