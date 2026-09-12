<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_collaboration\Utility;

use Drupal\Core\Entity\EntityStorageException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides wrapper for easly handling the JSON response of the exceptions.
 */
class ExceptionResponse {

  /**
   * Handls the entity storage exceptions.
   *
   * @param \Drupal\Core\Entity\EntityStorageException $exception
   *   The exception.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The error response.
   */
  public static function entityStorage(EntityStorageException $exception): JsonResponse {
    $message = $exception->getMessage() ?? 'Unable to store the entity';

    return static::getErrorResponse($message, Response::HTTP_INSUFFICIENT_STORAGE);
  }

  /**
   * The generic error reponse method.
   *
   * @param string $message
   *   The messages to be passsed in error.
   * @param int $status_code
   *   The status code to be used.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The error response.
   */
  public static function getErrorResponse(string $message, int $status_code): JsonResponse {
    return new JsonResponse(['error' => $message], $status_code);
  }

}
