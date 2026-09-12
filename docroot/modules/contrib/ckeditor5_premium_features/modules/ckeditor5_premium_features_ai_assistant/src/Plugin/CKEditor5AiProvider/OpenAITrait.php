<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types = 1);

namespace Drupal\ckeditor5_premium_features_ai_assistant\Plugin\CKEditor5AiProvider;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait OpenAITrait {

  /**
   * Handle streamed completions request.
   *
   * @param array $requestData
   *   Request data array.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The completions response.
   */
  protected function processStreamed(array $requestData): Response {
    $client = $this->getClient();
    $stream = $client->chat()->createStreamed($requestData);

    return new StreamedResponse(function () use ($stream) {
      foreach ($stream as $data) {
        echo json_encode($data->toArray()), PHP_EOL;
        ob_flush();
        flush();
      }
    }, 200, [
        'Cache-Control' => 'no-cache, must-revalidate',
        'Content-Type' => 'text/event-stream',
        'X-Accel-Buffering' => 'no',
    ]);
  }

  /**
   * Handle regular completions request.
   *
   * @param array $requestData
   *   Request data array.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The completions response.
   */
  protected function processRegular(array $requestData): Response {
    $client = $this->getClient();
    $chat = $client->chat()->create($requestData)->toArray();

    return new Response(json_encode($chat), 200, [
        'Cache-Control' => 'no-cache, must-revalidate',
        'Content-Type' => 'text/event-stream',
        'X-Accel-Buffering' => 'no',
    ]);
  }

  /**
   * Return render array of common Open AI config fields.
   *
   * @param array $default
   *   Default form API settings. Here used to optionally mark fields as disabled.
   *
   * @return array
   *   The render array for model and parameters fields.
   */
  protected function getParametersFields(array $default): array {

    $versionChecker = \Drupal::service('ckeditor5_premium_features.core_library_version_checker');
    $version = $versionChecker->getCurrentVersion();
    $documentationUrl = 'https://ckeditor.com/docs/ckeditor5/' . $version . '/features/ai-assistant/ai-assistant-integration.html#supported-models';

    $fields = [];
    $fields['model'] = [
        "#type" => "textfield",
        "#title" => $this->t("Model"),
        "#description" => $this->t('If empty, the default model depends on the CKEditor 5 version (<a href="@documentation" target="_blank">see documentation</a>)<br />
                         You can find more information about offered models in the <a href="https://platform.openai.com/docs/models/" target="_blank">OpenAI documentation.</a>',
                          ['@documentation' => $documentationUrl]),
    ] + $default;
    $fields['parameters'] = [
        "#type" => "textarea",
        "#title" => $this->t("Request parameters"),
        "#description" => $this->t('Additional configuration parameters for the AI service request. Use it to customize how the AI service generates responses. <br />
Defaults to:') .
            '<pre>
{
  "max_tokens": 2000,
  "temperature": 1,
  "top_p": 1,
  "stream": true
}
</pre>',
    ] + $default;
    return $fields;
  }

  /**
   * Checks if the required library is installed and displays warning message in case it's missing,
   *
   * @param string $provider
   *  The provider name to be displayed in the message.
   *
   * @return bool
   *   TRUE if the required dependencies are installed, FALSE otherwise.
   */
  public static function isInstalled(string $provider = ''): bool {
    if (ckeditor5_premium_features_check_dependency_class('\OpenAI')) {
      return TRUE;
    }

    $message = t('@provider is disabled because its required dependency <code>openai-php/client</code> is not installed.', [
      '@provider' => $provider,
    ]);
    ckeditor5_premium_features_display_missing_dependency_warning($message);
    return FALSE;
  }

}
