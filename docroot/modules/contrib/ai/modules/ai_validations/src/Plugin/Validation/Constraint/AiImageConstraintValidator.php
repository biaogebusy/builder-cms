<?php

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * AiImage constraint.
 */
final class AiImageConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiPluginManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Creates a new AiProvider instance.
   *
   * @param \Drupal\ai\AiProviderPluginManager $aiPluginManager
   *   The ai provider.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(AiProviderPluginManager $aiPluginManager, EntityTypeManager $entityTypeManager) {
    $this->aiPluginManager = $aiPluginManager;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider'),
      $container->get('entity_type.manager'),
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
    if (empty($data)) {
      // If no actual image is set, it should pass since it wasn't required.
      return;
    }
    $storage = $this->entityTypeManager->getStorage('file');
    $file = $storage->load($data);
    if (empty($file)) {
      $this->context->addViolation('No file accessible', []);
      return;
    }
    $provider = $this->aiPluginManager->loadProviderFromSimpleOption($constraint->provider);

    $prompt = $constraint->prompt . PHP_EOL . $constraint->message;
    // Format the requested CHatInput for textual validation.
    $image = new ImageFile();
    $image->setFileFromFile($file);
    $messages = new ChatInput([
      new ChatMessage('system', $prompt),
      new ChatMessage('user', $data, [$image]),
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
