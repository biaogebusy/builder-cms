<?php

namespace Drupal\ai_assistant_api\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsInputInterface;
use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput;
use Drupal\ai_assistant_api\AiAssistantActionPluginManager;
use Drupal\ai_assistant_api\AiAssistantApiCacheTrait;
use Drupal\ai_assistant_api\Entity\AiAssistant;
use Drupal\ai_assistant_api\Event\PrepromptSystemRoleEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * For building the assistant message.
 */
class AssistantMessageBuilder {

  use AiAssistantApiCacheTrait;

  /**
   * The AI assistant entity.
   *
   * @var \Drupal\ai_assistant_api\Entity\AiAssistant
   */
  protected AiAssistant $assistant;

  /**
   * The thread id.
   *
   * @var string
   */
  protected string $threadId;

  /**
   * Extra context.
   *
   * @var array
   */
  protected array $context;

  /**
   * Constructs a new AssistantMessageBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\ai_assistant_api\AiAssistantActionPluginManager $actions
   *   The actions service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Controller\TitleResolverInterface $titleResolver
   *   The title resolver.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Site\Settings $settings
   *   The settings service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    protected EntityTypeManager $entityTypeManager,
    protected AiAssistantActionPluginManager $actions,
    protected EventDispatcherInterface $eventDispatcher,
    protected AccountProxyInterface $currentUser,
    protected TitleResolverInterface $titleResolver,
    protected RequestStack $requestStack,
    protected LanguageManagerInterface $languageManager,
    protected ConfigFactoryInterface $configFactory,
    protected Settings $settings,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * Builds the assistant message.
   *
   * @param \Drupal\ai_assistant_api\Entity\AiAssistant $ai_assistant
   *   The AI assistant entity.
   * @param string $thread_id
   *   The thread id.
   * @param bool $include_pre_prompt
   *   Whether to include the pre-prompt message.
   * @param array $context
   *   Extra context.
   *
   * @return string
   *   The assistant message.
   */
  public function buildMessage(AiAssistant $ai_assistant, $thread_id, $include_pre_prompt = FALSE, $context = []): string {
    $this->context = $context;
    $this->assistant = $ai_assistant;
    $this->threadId = $thread_id;
    // Get the system prompt.
    $assistant_message = $ai_assistant->get('system_prompt');
    // If the site isn't configured to use custom prompts, override with the
    // latest version of the prompt from the module.
    if (!$this->settings->get('ai_assistant_custom_prompts', FALSE)) {
      if ($assistant_message = $this->cacheGet('system_prompt')) {
        $assistant_message = $assistant_message->data;
      }
      else {
        $path = $this->moduleHandler->getModule('ai_assistant_api')->getPath() . '/resources/';
        $assistant_message = file_get_contents($path . 'system_prompt.txt');
        $this->cacheSet('system_prompt', $assistant_message);
      }
    }

    // First replace the instructions.
    $instructions = ($ai_assistant->get('instructions')) ?? '';
    $assistant_message = str_replace('[instructions]', $instructions, $assistant_message);

    // If the pre-prompt message should be included and it exists, replace it.
    $pre_prompt_content = $this->prePrompt();
    $pre_prompt = ($include_pre_prompt && $pre_prompt_content) ? $pre_prompt_content : '';
    $assistant_message = str_replace('[pre_action_prompt]', $pre_prompt, $assistant_message);

    foreach ($this->getPrePromptDrupalContext() as $key => $replace) {
      $assistant_message = str_replace('[' . $key . ']', is_null($replace) ? '' : $replace, $assistant_message);
    }

    return $assistant_message;
  }

  /**
   * Runs the pre prompt to figure out what to do.
   */
  protected function prePrompt() {
    if ($this->assistant->get('use_function_calling')) {
      return "";
    }
    $preprompt = $this->assistant->get('pre_action_prompt');
    // If configured to use the updated prompts from the module, override.
    if (!$this->settings->get('ai_assistant_custom_prompts', FALSE)) {
      if ($preprompt = $this->cacheGet('pre_action_prompt')) {
        $preprompt = $preprompt->data;
      }
      else {
        $path = $this->moduleHandler->getModule('ai_assistant_api')->getPath() . '/resources/';
        $preprompt = file_get_contents($path . 'pre_action_prompt.txt');
        $this->cacheSet('pre_action_prompt', $preprompt);
      }
    }

    $actions = $this->getPreparedActions();
    $usage_instructions = $this->getUsageInstructions();
    $pre_prompt = str_replace([
      '[learning_examples]',
      '[list_of_actions]',
      '[usage_instruction]',
    ], [
      $this->getFewShotExamples(),
      $actions,
      $usage_instructions,
    ], $preprompt);

    foreach ($this->getPrePromptDrupalContext() as $key => $replace) {
      $pre_prompt = str_replace('[' . $key . ']', is_null($replace) ? '' : $replace, $pre_prompt);
    }

    $event = new PrepromptSystemRoleEvent($pre_prompt);
    $this->eventDispatcher->dispatch($event, PrepromptSystemRoleEvent::EVENT_NAME);
    $pre_prompt = $event->getSystemPrompt();
    return $pre_prompt;
  }

  /**
   * Get a list of prepared actions.
   *
   * @return string
   *   A string representation of the actions for AI prompts.
   */
  public function getPreparedActions() {
    $actions = $this->actions->listAllActions($this->assistant->get('actions_enabled'));
    $enabled = array_keys($this->assistant->get('actions_enabled'));
    $prepared = '';
    foreach ($actions as $action) {
      if (!in_array($action['plugin'], $enabled)) {
        continue;
      }
      $prepared .= "* action: " . $action['id'] . ", label: " . $action['label'] . ", description: " . $action['description'] . ", plugin: " . $action['plugin'] . "\n";
    }

    $contexts = $this->actions->listAllContexts($this->assistant, $this->threadId, $this->assistant->get('actions_enabled'));
    if (count($contexts)) {
      $prepared .= "\n";
      $prepared .= "The following are contexts for the actions:\n\n";
      foreach ($contexts as $context) {
        $prepared .= $context['title'] . "\n";
        $prepared .= '* ' . implode("\n* ", $context['description']) . "\n\n";
      }
    }
    return $prepared;
  }

  /**
   * Gets all the few shot examples of the installed actions.
   *
   * @return string
   *   The few shot examples string.
   */
  public function getFewShotExamples() {
    $enabled_actions = $this->assistant->get('actions_enabled');
    $text = '';
    foreach ($enabled_actions as $action => $config) {
      $instance = $this->actions->createInstance($action, $config);
      $examples = $instance->provideFewShotLearningExample();
      foreach ($examples as $example) {
        $text .= $example['description'] . "\n";
        $text .= json_encode($example['schema']) . "\n\n";
      }
    }
    return $text;
  }

  /**
   * Get a list of usage instructions.
   *
   * @return string
   *   A string representation of the usage instructions.
   */
  public function getUsageInstructions() {
    return implode("\n", $this->actions->listAllUsageInstructions($this->assistant->get('actions_enabled')));
  }

  /**
   * Get preprompt Drupal context.
   *
   * @return string[]
   *   This is the Drupal context that you can add to the pre prompt.
   */
  public function getPrePromptDrupalContext() {
    $context = [];
    $current_request = $this->requestStack->getCurrentRequest();
    $context['is_logged_in'] = $this->currentUser->isAuthenticated() ? 'is logged in' : 'is not logged in';
    $context['user_roles'] = implode(', ', $this->currentUser->getRoles());
    $context['user_id'] = $this->currentUser->id();
    $context['user_name'] = $this->currentUser->getDisplayName();
    $context['user_language'] = $this->currentUser->getPreferredLangcode();
    $context['user_timezone'] = $this->currentUser->getTimeZone();
    $context['page_title'] = (string) $this->titleResolver->getTitle($current_request, $current_request->attributes->get('_route_object'));
    $context['page_path'] = $this->context['current_route'] ?? $current_request->getRequestUri();
    $context['page_language'] = $this->languageManager->getCurrentLanguage()->getId();
    $context['site_name'] = $this->configFactory->get('system.site')->get('name');

    return $context;
  }

  /**
   * Load the function calls available.
   *
   * @return \Drupal\ai\OperationType\Chat\Tools\ToolsInputInterface
   *   The tools input.
   */
  public function getFunctionCalls(): ToolsInputInterface {
    $tools = [];
    $enabled_actions = $this->assistant->get('actions_enabled');
    $functions = [];
    foreach ($enabled_actions as $action => $config) {
      $instance = $this->actions->createInstance($action, $config);
      $properties = [];
      foreach ($instance->getFunctionCallSchema() as $name => $schema) {
        // Create new abstracted function calls.
        $property = new ToolsPropertyInput($name, $schema);
        $properties[$name] = $property;
      }
      $function = new ToolsFunctionInput($action);
      $function->setProperties($properties);
      $functions[] = $function;
    }
    $tools = new ToolsInput($functions);
    return $tools;
  }

}
