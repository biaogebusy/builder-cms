<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types = 1);

namespace Drupal\ckeditor5_premium_features_ai_assistant\Controller;

use Drupal\ckeditor5_premium_features_ai_assistant\Utility\AiAssistantHelper;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for CKEditor 5 Premium Features AI Assistant services.
 */
final class AiAssistantProviderProxyController extends ControllerBase {

  /**
   * Constructs the object.
   *
   * @param \Drupal\ckeditor5_premium_features_ai_assistant\Utility\AiAssistantHelper $aiAssistantHelper
   *   Helper for AI Assistant client.
   */
  public function __construct(private readonly AiAssistantHelper $aiAssistantHelper) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('ckeditor5_premium_features_ai_assistant.ai_assistant_helper')
    );
  }

  /**
   * Builds the response.
   */
  public function __invoke(Request $request): Response {
    $aiProvider = $this->aiAssistantHelper->getProvider();
    if (!$aiProvider) {
      return new Response('No AI service available.', 501);
    }
    return $aiProvider->processRequest($request);
  }

}
