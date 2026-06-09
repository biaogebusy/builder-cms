<?php

namespace Drupal\ai_chatbot\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_assistant_api\AiAssistantApiRunner;
use Drupal\ai_assistant_api\Data\UserMessage;
use Drupal\Component\Utility\Xss;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\ai\Response\AiStreamedResponse;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides a chat bot.
 */
class ChatForm extends FormBase {

  use DependencySerializationTrait;

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
   * Construct the chat.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\ai_assistant_api\AiAssistantApiRunner $aiAssistantRunner
   *   The AI Assistant API client.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatcher
   *   The route match.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AiAssistantApiRunner $aiAssistantRunner,
    private readonly RouteMatchInterface $routeMatcher,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('ai_assistant_api.runner'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_foundation_chat';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Set the assistant if its not set.
    $context = [];
    foreach ($this->routeMatcher->getParameters()->all() as $key => $data) {
      $context[$key] = $this->routeMatcher->getParameter($key);
    }
    // Setup the assistant.
    $this->aiAssistantRunner->setContext($context);

    if (!$this->getRequest()->isXmlHttpRequest()) {
      // Set the assistant id if its the page load.
      $form['#attached']['drupalSettings']['ai_chatbot']['assistant_id'] = $this->aiAssistantRunner->getAssistant()->id();
      $form['#attached']['drupalSettings']['ai_chatbot']['thread_id'] = $this->aiAssistantRunner->getThreadsKey();
    }

    $response_id = Html::getId($form_state->getBuildInfo()['block_id'] . '-response');

    $form['query'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Ask me a question'),
      '#title_display' => 'invisible',
      '#attributes' => [
        'placeholder' => $this->t('Ask me a question'),
        'class' => ['chat-form-query'],
      ],
      '#rows' => 1,
    ];

    // Make it possible to clear history.
    if ($this->aiAssistantRunner->getAssistant()->get('allow_history') == 'session_one_thread') {
      $form['clear_history'] = [
        '#type' => 'submit',
        '#value' => $this->t('Clear History'),
        '#attributes' => [
          'class' => ['chat-form-clear-history'],
        ],
      ];
    }

    $form['thread_id'] = [
      '#type' => 'hidden',
      '#default_value' => '',
      '#attributes' => [
        'class' => ['chat-form-thread-id'],
      ],
    ];

    $form['#attached']['library'][] = 'ai_chatbot/form-stream';

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
      '#attributes' => [
        'data-ai-ajax' => $response_id,
        'class' => ['chat-form-send'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->aiAssistantRunner->setThreadsKey($form_state->getValue('thread_id'));
    // Set the user message.
    $this->aiAssistantRunner->setUserMessage(new UserMessage($form_state->getValue('query')));

    // Send the query to OpenAI.
    if ($this->getRequest()->isXmlHttpRequest()) {
      try {
        // Process.
        $response = $this->aiAssistantRunner->process();
        // If its a failure, the variable is a string, just output;.
        if ($response->getNormalized() instanceof ChatMessage) {
          $output = $response->getNormalized()->getText();
          // Sanitize the output.
          $output = Xss::filter($output, $this->allowedTags);
          // Show structured results if wanted.
          if ($this->getChatConfig($form_state)['show_structured_results']) {
            $structured = $this->aiAssistantRunner->getStructuredResults();
            if ($structured) {
              $output .= "\n\n<details>\n\n```\n" . Yaml::dump($structured, 10) . "\n```\n\n</details>";
            }
          }
          $http_response = new Response($output);
          $this->aiAssistantRunner->setAssistantMessage($output);
          $form_state->setResponse($http_response);
        }
        else {
          $http_response = new AiStreamedResponse();
          $http_response->setCallback(function () use ($response, $form_state) {
            $full_response = "";
            $this->aiAssistantRunner->startSession();
            foreach ($response->getNormalized() as $message) {
              echo $message->getText();
              $full_response .= $message->getText();
              flush();
            }
            // Sanitize the full response.
            $full_response = Xss::filter($full_response, $this->allowedTags);
            // Show structured results if wanted.
            if ($this->getChatConfig($form_state)['show_structured_results']) {
              $structured = $this->aiAssistantRunner->getStructuredResults();
              if ($structured) {
                echo "\n\n<details>\n\n```\n" . Yaml::dump($structured, 10) . "\n```\n\n</details>";
                $full_response .= "\n\n<details>\n\n```\n" . Yaml::dump($structured, 10) . "\n```\n\n</details>";
                flush();
              }
            }
            $this->aiAssistantRunner->setAssistantMessage($full_response);
          });
          $form_state->setResponse($http_response);
        }
      }
      catch (\Exception $exception) {
        $http_response = new Response('Error: ' . $exception->getMessage());
        $form_state->setResponse($http_response);
      }
    }
    else {
      $response = $this->aiAssistantRunner->process();
      $form_state->setRebuild();
      $form_state->set('response', $response->getNormalized()->getText());
      $this->aiAssistantRunner->setAssistantMessage($response->getNormalized()->getText());
    }
  }

  /**
   * Get all the Chat config from build info with defaults.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state to get build info from.
   *
   * @return array
   *   The array of chat config with defaults where required.
   */
  protected function getChatConfig(FormStateInterface $form_state) {
    $config = $form_state->getBuildInfo()['chat_config'] ?? [];
    return $config;
  }

}
