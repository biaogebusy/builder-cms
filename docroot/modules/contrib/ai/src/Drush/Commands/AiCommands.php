<?php

declare(strict_types=1);

namespace Drupal\ai\Drush\Commands;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Drush commands that integrate with the AI module.
 */
class AiCommands extends DrushCommands {

  /**
   * The default system prompt to use.
   *
   * @var string
   */
  const DEFAULT_SYSTEM_PROMPT = <<<EOD
    You are a Drupal assistant, capable of answer questions about Drupal and Drush. When responding, make sure...

    1. To keep all answers brief, and in ANSI
    2. When highlighting text, wrap it in <info></info> tags
    3. When output errors, use <error></error> tags
    4. When writing optional text, use <comment></comment> tags
    5. When posing questions, use <question></question> tags
    6. When outputting a link, write the link in this format: <href=https://drupal.org>Drupal</>
    EOD;

  /**
   * The constructor.
   *
   * @param \Drupal\ai\AiProviderPluginManager $aiProviderPluginManager
   *   The AI provider plugin manager.
   */
  public function __construct(
    protected AiProviderPluginManager $aiProviderPluginManager,
  ) {
    parent::__construct();
  }

  /**
   * Return an instance of these Drush commands.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   *
   * @return \Drupal\ai\Drush\Commands\AiCommands
   *   The instance of Drush commands.
   */
  public static function create(ContainerInterface $container): AiCommands {
    return new AiCommands(
      $container->get('ai.provider')
    );
  }

  /**
   * Send a message through to an AI provider.
   */
  #[CLI\Command(name: 'ai:chat', aliases: ['chat', 'ai'])]
  #[CLI\Argument(name: 'input', description: 'The message to send to the chat.')]
  #[CLI\Option(name: 'provider', description: 'Indicates a provider to use other than the default.')]
  #[CLI\Option(name: 'model', description: 'Indicates which model to use, other than the default.')]
  #[CLI\Option(name: 'system', description: 'Indicates the system message to use, other than the default.')]
  #[CLI\Usage(name: 'drush ai:chat "Hello, how are you?"', description: 'Sends a message to your chat provider.')]
  #[CLI\Usage(name: 'drush ai', description: 'Interactive mode - type your message and press Ctrl+D when done.')]
  #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
  public function chat(
    ?string $input = NULL,
    array $options = [
      'provider' => self::OPT,
      'model' => self::OPT,
      'system' => self::OPT,
    ],
  ): void {

    // Retrieve the AI Provider and the options.
    $defaults = $this->aiProviderPluginManager->getDefaultProviderForOperationType('chat');
    $provider_id = empty($options['provider']) ? $defaults['provider_id'] : $options['provider'];
    $model_id = empty($options['model']) ? $defaults['model_id'] : $options['model'];
    $system_message = !empty($options['system']) ? $options['system'] : self::DEFAULT_SYSTEM_PROMPT;

    // Validate the provider ID and model ID.
    if (empty($provider_id) || empty($model_id)) {
      $this->logger()->error(dt('No default AI provider or model set'));
      return;
    }

    // Build the provider and the chat message.
    try {
      $provider = $this->aiProviderPluginManager->createInstance($provider_id);
    }
    catch (\Exception $e) {
      $this->logger()->error(dt('Unable to fetch provider: @message', ['@message' => $e->getMessage()]));
      return;
    }

    // Check if input is empty and handle interactive mode.
    if (empty($input)) {
      $this->output()->writeln("<info>Interactive AI Chat Mode</info>");
      $this->output()->writeln("<comment>Type your message below. Press Ctrl+D (EOF) when finished:</comment>");
      $this->output()->writeln("");

      $input = '';
      while ($line = fgets(STDIN)) {
        $input .= $line;
      }

      // Trim any trailing whitespace.
      $input = trim($input);

      if (empty($input)) {
        $this->output()->writeln("<error>No input provided. Exiting.</error>");
        return;
      }

      $this->output()->writeln("");
    }

    // Create the chat input with the system message and user input.
    $messages = new ChatInput([
      new ChatMessage('system', $system_message),
      new ChatMessage('user', $input),
    ]);

    // Show progress indicator.
    $this->output()->write("<info>🤖 AI is thinking...</info>");

    // Output the message.
    try {
      $message = $provider->chat($messages, $model_id)->getNormalized();
    }
    catch (\Exception $e) {
      // Clear the progress loader.
      $this->output()->write("\r" . str_repeat(' ', 50) . "\r");
      $this->logger()->error(dt('Unable to fetch response: @message', ['@message' => $e->getMessage()]));
      return;
    }

    // Clear the progress loader.
    $this->output()->write("\r" . str_repeat(' ', 50) . "\r");

    // Output the response with proper formatting.
    $this->output()->writeln("<info>💬 AI Response:</info>");
    $this->output()->writeln("");

    $lines = explode("\n", $message->getText());
    foreach ($lines as $line) {
      $this->output()->writeln($line);
    }

    $this->output()->writeln("");
  }

}
