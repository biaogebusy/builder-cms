<?php

declare(strict_types=1);

namespace Drupal\ai_chatbot\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\ai\Exception\AiBadRequestException;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiRequestErrorException;
use Drupal\ai\Exception\AiSetupFailureException;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIterator;
use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Drupal\ai_assistant_api\AiAssistantApiRunner;
use Drupal\ai_assistant_api\Data\UserMessage;
use Drupal\ai_assistant_api\Entity\AiAssistant;
use Drupal\ai_chatbot\Service\MessagesButtons;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\ai\Response\AiStreamedResponse;
use Symfony\Component\Yaml\Yaml;

/**
 * Returns responses for AI Deepchat routes.
 */
class DeepChatApi extends ControllerBase {

  /**
   * All buttons available for the assistant.
   *
   * @var array
   */
  protected array $buttons = [];

  /**
   * Should show structured results.
   *
   * @var bool
   */
  protected bool $showStructuredResults = FALSE;

  /**
   * Allowed tags.
   *
   * These are the allowed tags for the LLM response. We allow anything that
   * can be expressed in markdown.
   *
   * @var array
   */
  protected array $allowedTags = [
    'a',
    'b',
    'blockquote',
    'br',
    'code',
    'del',
    'em',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'hr',
    'i',
    'img',
    'li',
    'ol',
    'p',
    'pre',
    'strong',
    'ul',
  ];

  /**
   * Constructs a new DeepChatApi object.
   *
   * @param \Drupal\ai_assistant_api\AiAssistantApiRunner $aiAssistantClient
   *   The AI Assistant API client.
   * @param \Drupal\ai_chatbot\Service\MessagesButtons $messagesButtons
   *   The messages buttons render service.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrfTokenGenerator
   *   The CSRF token generator.
   * @param \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager $functionCallPluginManager
   *   The function call plugin manager.
   */
  public function __construct(
    protected AiAssistantApiRunner $aiAssistantClient,
    protected MessagesButtons $messagesButtons,
    protected CsrfTokenGenerator $csrfTokenGenerator,
    protected FunctionCallPluginManager $functionCallPluginManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('ai_assistant_api.runner'),
      $container->get('ai_chatbot.buttons'),
      $container->get('csrf_token'),
      $container->get('plugin.manager.ai.function_calls'),
    );
  }

  /**
   * Handles the DeepChat API request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|\Drupal\ai\Response\AiStreamedResponse
   *   The JSON or Streamed response.
   */
  public function api(Request $request): JsonResponse|AiStreamedResponse {
    // Get the request content and decode it.
    $content = $request->getContent();
    $data = Json::decode($content);

    // Retrieve the assistant_id from request payload.
    if (!isset($data['assistant_id'])) {
      // Assistant ID is required.
      return new JsonResponse(['error' => $this->t('assistant_id is required.')], 422);
    }

    // Load the AiAssistant entity.
    $assistant = $this->entityTypeManager()->getStorage('ai_assistant')->load($data['assistant_id']);

    if (!$assistant instanceof AiAssistant) {
      return new JsonResponse(['error' => $this->t('Invalid assistant ID.')], 422);
    }

    // Set the assistant in the AiAssistantApiRunner.
    $this->aiAssistantClient->setAssistant($assistant);

    if (isset($data['stream']) && $data['stream'] === 1) {
      $this->aiAssistantClient->streamedOutput(TRUE);
    }

    // Optionally, set the thread_id if provided.
    if (isset($data['thread_id'])) {
      $this->aiAssistantClient->setThreadsKey($data['thread_id']);
    }

    // Set the context if provided.
    if (isset($data['contexts']) && is_array($data['contexts'])) {
      $this->aiAssistantClient->setContext($data['contexts']);
    }

    if (isset($data['verbose_mode']) && $data['verbose_mode']) {
      $this->aiAssistantClient->setVerboseMode(TRUE);
    }

    // Check if 'messages' array is provided.
    if (isset($data['messages']) && is_array($data['messages'])) {
      $messages = $data['messages'];

      // Extract user messages.
      $conversation = [];
      foreach ($messages as $message) {
        if (isset($message['role'], $message['text'])) {
          $role = $message['role'];
          $text = $message['text'];
          if ($role === 'user') {
            $conversation[] = new UserMessage($text);
          }
        }
      }

      if (empty($conversation)) {
        return new JsonResponse(['error' => $this->t('No user messages provided.')], 400);
      }

      // Set the latest user message.
      $latestUserMessage = end($conversation);
      $this->aiAssistantClient->setUserMessage($latestUserMessage);
      $this->aiAssistantClient->setThrowException(TRUE);

      // Set the structured result data.
      $this->showStructuredResults = isset($data['structured_results']) && $data['structured_results'];

      // Set the copy button if wanted.
      if (isset($data['show_copy_icon']) && $data['show_copy_icon']) {
        $this->buttons[] = [
          'svg' => $this->moduleHandler()->getModule('ai_chatbot')->getPath() . '/assets/copy-icon.svg',
          'weight' => 1,
          'class' => ['copy'],
          'alt' => $this->t('Copy message'),
          'title' => $this->t('Copy message'),
        ];
      }

      // Process the user's message.
      try {
        $response = $this->aiAssistantClient->process();

        // Handle the response, which could be a ChatMessage or Stream.
        $normalizedResponse = $response->getNormalized();

        // Decide response type based on the request.
        if ($normalizedResponse instanceof ChatMessage) {
          return $this->createResponse($normalizedResponse);
        }
        else {
          return $this->createStreamedResponse($normalizedResponse);
        }
      }
      catch (\Exception $e) {
        $error_message = $assistant->get('error_message');

        // Try to find a specific error message to use.
        if ($e instanceof AiBadRequestException) {
          $error_message = $assistant->get('specific_error_messages')['AiBadRequestException'] ?? $error_message;
        }
        elseif ($e instanceof AiRateLimitException) {
          $error_message = $assistant->get('specific_error_messages')['AiRateLimitException'] ?? $error_message;
        }
        elseif ($e instanceof AiQuotaException) {
          $error_message = $assistant->get('specific_error_messages')['AiQuotaException'] ?? $error_message;
        }
        elseif ($e instanceof AiSetupFailureException) {
          $error_message = $assistant->get('specific_error_messages')['AiSetupFailureException'] ?? $error_message;
        }
        elseif ($e instanceof AiRequestErrorException) {
          $error_message = $assistant->get('specific_error_messages')['AiRequestErrorException'] ?? $error_message;
        }

        // Log the error.
        $this->getLogger('ai_chatbot')->error('The chatbot had an error: @message', ['@message' => $e->getMessage()]);

        // Otherwise use the default error message.
        return new JsonResponse([
          'error' => $error_message,
        ], 500);

      }
    }
    else {
      // No messages provided in the request.
      return new JsonResponse(['error' => $this->t('No messages provided.')], 400);
    }
  }

  /**
   * Returns a normal response.
   */
  public function createResponse($normalizedResponse): JsonResponse {
    $assistantResponseText = $normalizedResponse->getText();
    // Set the assistant message for logging or further processing.
    // Only if it does not contain tools.
    if (empty($normalizedResponse->getTools())) {
      $this->aiAssistantClient->setAssistantMessage($assistantResponseText);
    }
    // Change the response if needed.
    $extra = $this->moduleHandler()->invokeAll('deepchat_prepend_message', [
      $assistantResponseText,
      'text',
      $this->aiAssistantClient->getAssistant()->id(),
      $this->aiAssistantClient->getThreadsKey(),
    ]);

    $converter = $this->getCommonMarkConverter();
    $assistantResponseText = $this->rewriteMarkdownMessage($assistantResponseText);
    $assistantResponseText = $converter ? $converter->convert($assistantResponseText) : $assistantResponseText;
    $assistantResponseText = (string) $assistantResponseText;
    // Everything after this is controlled by modules, so we sanitize here.
    $assistantResponseText = Xss::filter($assistantResponseText, $this->allowedTags);
    if (empty($normalizedResponse->getTools())) {
      $assistantResponseText .= $this->renderStructuredResults();
      if (!empty($extra)) {
        foreach ($extra as $message) {
          $assistantResponseText .= $message;
        }
      }

      $assistantResponseText .= $this->messagesButtons->getRenderedButtons($this->buttons, $this->aiAssistantClient->getAssistant()->id(), $this->aiAssistantClient->getThreadsKey());
    }
    else {
      // If its empty string, we return that a tool was used.
      $agents = [];
      foreach ($normalizedResponse->getTools() as $tool) {
        $agents[$tool->getName()] = $this->functionCallPluginManager->getFunctionCallFromFunctionName($tool->getName())->getPluginDefinition()['name'] ?? $tool->getName();
      }
      $assistantResponseText = $this->t('Calling @tools', [
        '@tools' => implode(', ', $agents),
      ]) . $assistantResponseText;
    }

    // Default to JSON response.
    return new JsonResponse([
      'html' => $assistantResponseText,
      'should_continue' => !empty($normalizedResponse->getTools()),
    ]);
  }

  /**
   * Creates a StreamedResponse for the assistant's reply.
   *
   * @param \Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface $streamedMessages
   *   The text generated by the AI assistant.
   *
   * @return \Drupal\ai\Response\AiStreamedResponse
   *   The streamed response.
   */
  private function createStreamedResponse(StreamedChatMessageIterator $streamedMessages): AiStreamedResponse {
    $response = new AiStreamedResponse(NULL, 200, [
      'Content-Type' => 'text/event-stream',
    ]);

    $response->setCallback(function () use ($streamedMessages) {
      $saveMessage = '';

      // Make sure to start session.
      $this->aiAssistantClient->startSession();
      foreach ($streamedMessages as $chunk) {
        $saveMessage .= $chunk->getText();
        // Send each chunk.
        $this->createSseMessage($saveMessage, TRUE);
      }

      $this->aiAssistantClient->setAssistantMessage($saveMessage);

      $extra = $this->moduleHandler()->invokeAll('deepchat_prepend_message', [
        $saveMessage,
        'text',
        $this->aiAssistantClient->getAssistant()->id(),
        $this->aiAssistantClient->getThreadsKey(),
      ]);
      if (!empty($extra)) {
        foreach ($extra as $message) {
          $this->createSseMessage($message);
        }
      }
      // Send the structured results.
      $this->createSseMessage($this->renderStructuredResults());

      // Send the buttons.
      $this->createSseMessage($this->messagesButtons->getRenderedButtons($this->buttons, $this->aiAssistantClient->getAssistant()->id(), $this->aiAssistantClient->getThreadsKey()));
    });

    return $response;
  }

  /**
   * Gets an x-csrf-token back.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  public function setSession(Request $request): Response {
    $session = $request->getSession();
    // Create a session for the user if they are anonymous.
    if (!$session->isStarted()) {
      $session->start();
    }
    // Set a session variable.
    $session->set('deepchat', 'true');
    return new Response($this->csrfTokenGenerator->get("api/deepchat"));
  }

  /**
   * Rewrite markdown message.
   *
   * @param string $message
   *   The message to rewrite.
   *
   * @return string
   *   The rewritten message.
   */
  public function rewriteMarkdownMessage(string $message): string {
    // Find any link from a markdown message an iterate them.
    $pattern = '/\[(.*?)\]\((.*?)\)/';
    preg_match_all($pattern, $message, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      $link = trim($match[2]);
      $text = $match[1];
      // If the link does not start with a protocol or slash, add the base URL.
      if (strpos($link, '://') === FALSE && strpos($link, '/') !== 0) {
        $link = base_path() . $link;
      }
      // Sometimes it adds double slashes at the start.
      if (substr($link, 0, 2) === '//') {
        $link = substr($link, 1);
      }

      $message = str_replace($match[0], "[$text]($link)", $message);
    }

    return $message;
  }

  /**
   * Send SSE message.
   *
   * @param string $message
   *   The message to send.
   * @param bool $is_chunk
   *   If the message is a chunk.
   * @param string $type
   *   The type of message.
   */
  public function createSseMessage(string $message, bool $is_chunk = FALSE, string $type = 'html') {
    $message = $this->rewriteMarkdownMessage($message);
    $converter = $this->getCommonMarkConverter();
    if ($is_chunk) {
      // Send the chunk.
      $html = $converter ? $converter->convert($message)->__toString() : $message;
      // This part is generated by the LLM, so sanitize it.
      $html = Xss::filter($html, $this->allowedTags);
      if ($html) {
        echo 'data: ' . Json::encode([$type => $html, 'overwrite' => TRUE]) . "\n\n";
        flush();
      }
    }
    else {
      echo 'data: ' . Json::encode([$type => $message]) . "\n\n";
      flush();
    }
  }

  /**
   * Get the structured results if wanted.
   *
   * @return string
   *   The structured results.
   */
  public function renderStructuredResults(): string {
    $results = '';
    if ($this->showStructuredResults) {
      $structured = $this->aiAssistantClient->getStructuredResults();
      if ($structured) {
        // Add the button.
        $this->buttons[] = [
          'svg' => $this->moduleHandler()->getModule('ai_chatbot')->getPath() . '/assets/combine-left-right-icon.svg',
          'weight' => 1,
          'class' => ['structured-results'],
          'alt' => $this->t('Structured results'),
          'title' => $this->t('Structured results'),
        ];
        $results .= '<div class="structured-results-dump"><pre>' . Yaml::dump($structured, 10) . "</pre></div>";
      }
    }
    return $results;
  }

  /**
   * Gets the common mark converter if available.
   *
   * @return \League\CommonMark\CommonMarkConverter|null
   *   The common mark converter.
   */
  public function getCommonMarkConverter() {
    $converter = NULL;
    if (class_exists('League\CommonMark\CommonMarkConverter')) {
      // Ignore the non-use statement loading since this dependency may not
      // exist.
      // @codingStandardsIgnoreLine
      $converter = new \League\CommonMark\CommonMarkConverter();
    }
    return $converter;
  }

}
