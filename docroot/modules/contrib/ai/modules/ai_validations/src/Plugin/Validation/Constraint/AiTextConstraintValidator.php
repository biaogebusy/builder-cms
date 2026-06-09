<?php

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * AiText constraint.
 */
final class AiTextConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiPluginManager;

  /**
   * Creates a new AiProvider instance.
   *
   * @param \Drupal\ai\AiProviderPluginManager $aiPluginManager
   *   The ai provider.
   */
  public function __construct(AiProviderPluginManager $aiPluginManager) {
    $this->aiPluginManager = $aiPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $data, Constraint $constraint) {
    if (empty($constraint->provider)) {
      $this->context->addViolation('No AI provider specified to do validation', []);
      return;
    }

    if (is_null($data)) {
      $data = '';
    }

    $provider = $this->aiPluginManager->loadProviderFromSimpleOption($constraint->provider);

    $prompt = $constraint->prompt . PHP_EOL . $constraint->message;
    // Format the requested CHatInput for textual validation.
    $messages = new ChatInput([
      new ChatMessage('system', $prompt),
      new ChatMessage('user', $data),
    ]);
    // Give it to the AI.
    $model = $this->aiPluginManager->getModelNameFromSimpleOption($constraint->provider);
    $message = $provider->chat($messages, $model)->getNormalized();
    $response_ok = FALSE;
    if (str_contains($message->getText(), 'XTRUE')) {
      $response_ok = TRUE;
    }
    elseif (str_contains($message->getText(), 'XFALSE')) {
      $response_ok = FALSE;
    }

    if (!$response_ok) {
      $this->context->addViolation($constraint->message, []);
    }
  }

}
