<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Generator;

use Drupal\ckeditor5_premium_features\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides file name generator based on current node alias.
 */
class FileNameGenerator implements FileNameGeneratorInterface {

  public const DEFAULT_FILENAME = 'document';

  /**
   * Constructs a new BookNavigationCacheContext service.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   */
  public function __construct(
    protected RouteMatchInterface $routeMatch
  ) {
  }

  /**
   * Generate file name based entity alias.
   */
  public function generateFromRequest(): string {
    $entity = NULL;
    $params = $this->routeMatch->getParameters()->all();
    foreach ($params as $param) {
      if ($param instanceof EntityInterface) {
        $entity = $param;
        break;
      }
    }
    try {
      if ($entity) {
        $label = $entity->label() ?: $this::DEFAULT_FILENAME;
        return $this->convertLabelToFileName($label);
      }
    }
    catch (\Exception $e) {
    }

    return self::DEFAULT_FILENAME;
  }

  /**
   * Add extension to filename.
   *
   * @param string $filename
   *   Filename.
   * @param string $extension
   *   Extension file.
   */
  public function addExtensionFile(string &$filename, string $extension): void {
    $extension = str_starts_with($extension, '.') ? $extension : '.' . $extension;
    $filename .= $extension;
  }

  /**
   * Convert entity label to friendly filename.
   *
   * @param string $label
   *   Entity label.
   *
   * @return string
   *   Converted filename.
   */
  public function convertLabelToFileName(string $label): string {
    return Html::cleanCssIdentifier($label);
  }

}
