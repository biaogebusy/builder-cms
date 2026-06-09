<?php

namespace Drupal\ai_ckeditor\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai_ckeditor\PluginInterfaces\AiCKEditorPluginInterface;
use Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\ai\Response\AiStreamedResponse;

/**
 * Returns responses for CKEditor integration routes.
 */
class AiRequest implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * Constructs the controller.
   *
   * @param \Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager $pluginManager
   *   AI CKEditor Plugin manager.
   * @param \Drupal\ai\AiProviderPluginManager $aiProviderManager
   *   AI Provider manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   Account proxy.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(
    protected readonly AiCKEditorPluginManager $pluginManager,
    protected readonly AiProviderPluginManager $aiProviderManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly AccountProxyInterface $account,
    LoggerChannelFactoryInterface $logger_factory,
    protected readonly MessengerInterface $messenger,
  ) {
    $this->logger = $logger_factory->get('ai_ckeditor');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.ai_ckeditor'),
      $container->get('ai.provider'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('logger.factory'),
      $container->get('messenger'),
    );
  }

  /**
   * Performs a request to AI for streamed output.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\editor\EditorInterface $editor
   *   The editor.
   * @param \Drupal\ai_ckeditor\PluginInterfaces\AiCKEditorPluginInterface $ai_ckeditor_plugin
   *   The CK Editor plugin.
   *
   * @return \Drupal\ai\Response\AiStreamedResponse|\Symfony\Component\HttpFoundation\Response
   *   The AI response.
   */
  public function doRequest(Request $request, EditorInterface $editor, AiCKEditorPluginInterface $ai_ckeditor_plugin): AiStreamedResponse|Response {
    $data = json_decode($request->getContent());

    try {
      $settings = $editor->getSettings();
      $configuration = $settings["plugins"]["ai_ckeditor_ai"]["plugins"];
      $preferred_model = $configuration[$ai_ckeditor_plugin->getPluginId()]['provider'];

      if ($preferred_model) {
        $ai_provider = $this->aiProviderManager->loadProviderFromSimpleOption($preferred_model);
        $ai_model = $this->aiProviderManager->getModelNameFromSimpleOption($preferred_model);
      }
      else {
        // Get the default provider.
        $default_provider = $this->aiProviderManager->getDefaultProviderForOperationType('chat');
        if (empty($default_provider['provider_id'])) {
          // If we got nothing return NULL.
          $this->messenger->addError($this->t('No AI provider is set for chat. Please configure one in the "Text format and editors settings" or setup a default Chat model in the %ai_settings_link.', [
            '%ai_settings_link' => Link::createFromRoute($this->t('AI settings'), 'ai.settings_form')
              ->toString(),
          ]));
          throw new \exception('No AI provider is set for chat. Please configure one in the AI default settings or in the ai_content settings form.');
        }
        $ai_provider = $this->aiProviderManager->createInstance($default_provider['provider_id']);
        $ai_model = $default_provider['model_id'];
      }

      // @todo Check config if user wants answers as HTML.
      /** @var \Drupal\filter\FilterFormatInterface $format */
      $format = $editor->getFilterFormat();
      $restrictions = $format->getHtmlRestrictions();

      // Supply the permitted HTML tags to the provider.
      if (!empty($restrictions) && !empty($restrictions['allowed'])) {
        $allowed_tags = "";

        foreach ($restrictions['allowed'] as $tag => $metadata) {
          $allowed_tags .= $tag . " ";
        }

        $data->prompt = "Format the answer using ONLY the following HTML tags: " . $allowed_tags . $data->prompt;
      }
      else {
        $data->prompt = "Format the answer using basic HTML formatting tags." . $data->prompt;
      }
      $data->prompt = "Do not try to use any image, video, or audio tags. Do not use backticks or ```html indicator." . $data->prompt;

      $messages = new ChatInput([
        new ChatMessage('user', $data->prompt),
      ]);

      // Add the system message.
      $messages->setStreamedOutput(TRUE);
      $messages->setSystemPrompt('You are helpful website assistant for content writing and editing. Do not give responses in the first, second or third person form. Do not add any commentary to the answer.');

      /** @var \Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface $response */
      $response = $ai_provider->chat($messages, $ai_model, ['ai_ckeditor'])->getNormalized();

      if ($response instanceof StreamedChatMessageIteratorInterface) {
        return new AiStreamedResponse(function () use ($response) {
          foreach ($response as $message) {
            echo $message->getText();
            flush();
          }
        });
      }
      else {
        return new Response($response->getText());
      }
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage());
    }

    return new Response("The request could not be completed.", Response::HTTP_BAD_REQUEST);
  }

}
