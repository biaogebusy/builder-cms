<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features_wproofreader\Utility;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Provides the interface for the WebSpellCheckerHandler.
 */
interface WebSpellCheckerHandlerInterface {

  /**
   * Validates if Service ID is valid.
   *
   * @param string $serviceId
   *   The WSC Service ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JsonResponse information if service id is valid.
   */
  public function validateServiceId(string $serviceId): JsonResponse;

  /**
   * Get available languages.
   *
   * @param string $serviceId
   *   The WSC Service ID.
   *
   * @return array
   *   Array with available languages.
   */
  public function getAvailableLanguages(string $serviceId): array;

  /**
   * Checks if Service ID is valid.
   *
   * @param string $serviceId
   *   The WSC Service ID.
   *
   * @return bool
   *   True if valid.
   */
  public function isServiceIdValid(string $serviceId): bool;

}
