<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Controller;

use Drupal\ckeditor5_premium_features\CKeditorPremiumLoggerChannelTrait;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\filter\FilterPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller exposing an endpoint for applying caption filter to content.
 */
class CaptionFilterController extends ControllerBase {

  use CKeditorPremiumLoggerChannelTrait;

  /**
   * Constructor.
   *
   * @param \Drupal\filter\FilterPluginManager $filterManager
   *   Filter plugin manager.
   */
  public function __construct(
    protected FilterPluginManager $filterManager,
  ) {
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.filter')
    );
  }

  /**
   * API endpoint for applying caption filter to content.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Current request object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response.
   */
  public function applyFilter(Request $request) {
    $args = $request->request;
    $content = $args->get('content');

    if (!$content) {
      return new AjaxResponse(NULL, 400);
    }

    try {
      // Decode JSON content if it's in JSON format
      if ($this->isJson($content)) {
        $content = Json::decode($content);
      }

      // Create an instance of the caption filter
      $captionFilter = $this->filterManager->createInstance('filter_caption');

      // Process the content through the caption filter
      $result = $captionFilter->process($content, 'en');

      // Get the processed text
      $processedContent = $result->getProcessedText();

      // Return the processed content
      return new AjaxResponse(['content' => $processedContent]);
    }
    catch (\Exception $e) {
      $this->logException('Exception occurred during caption filter application.', $e);
      return new AjaxResponse(NULL, 500);
    }
  }

  /**
   * Check if a string is valid JSON.
   *
   * @param string $string
   *   The string to check.
   *
   * @return bool
   *   TRUE if the string is valid JSON, FALSE otherwise.
   */
  protected function isJson($string) {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
  }

}
