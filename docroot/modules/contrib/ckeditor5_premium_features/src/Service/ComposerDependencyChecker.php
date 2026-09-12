<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Service;

/**
 * Service for checking if composer dependencies are installed.
 */
class ComposerDependencyChecker {

  /**
   * Map of library names to their class names for checking existence.
   *
   * @var array
   */
  protected array $libraryClassMap = [
    'firebase/php-jwt' => 'Firebase\JWT\JWT',
    'caxy/php-htmldiff' => 'Caxy\HtmlDiff\HtmlDiff',
    'openai-php/client' => 'OpenAI',
    'aws/aws-sdk-php' => 'Aws\AwsClient',
  ];

  /**
   * Checks if a specific library is installed.
   *
   * @param string $libraryName
   *   The name of the library to check.
   *
   * @return bool
   *   TRUE if the library is installed, FALSE otherwise.
   */
  public function isLibraryInstalled(string $libraryName): bool {
    if (!isset($this->libraryClassMap[$libraryName])) {
      return FALSE;
    }

    return class_exists($this->libraryClassMap[$libraryName], false);
  }

  /**
   * Gets a list of all available libraries that can be checked.
   *
   * @return array
   *   An array of library names.
   */
  public function getAvailableLibraries(): array {
    return array_keys($this->libraryClassMap);
  }

  /**
   * Gets a map of library names to their installation status.
   *
   * @return array
   *   An array with library names as keys and boolean values indicating
   *   whether they are installed.
   */
  public function getLibraryStatusMap(): array {
    $statusMap = [];
    foreach ($this->getAvailableLibraries() as $libraryName) {
      $statusMap[$libraryName] = $this->isLibraryInstalled($libraryName);
    }
    return $statusMap;
  }

}
