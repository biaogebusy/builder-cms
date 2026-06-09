<?php

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ImageClassification\ImageClassificationInput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * AiImage classification constraint.
 */
final class AiImageClassificationConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

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
    if (empty($constraint->model)) {
      $this->context->addViolation('No AI model specified to do validation', []);
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
    $provider = $this->aiPluginManager->loadProviderFromSimpleOption($constraint->model);
    // Format the requested Image classification for textual validation.
    $image = new ImageFile();
    $image->setFileFromFile($file);
    $messages = new ImageClassificationInput($image);
    // Give it to the AI.
    $model = $this->aiPluginManager->getModelNameFromSimpleOption($constraint->model);
    try {
      $classifications = $provider->imageClassification($messages, $model)->getNormalized();
    }
    catch (\Exception $e) {
      if ($constraint->na == 'fail') {
        $this->context->addViolation('AI provider failed to classify image', []);
      }
      return;
    }
    foreach ($classifications as $classification) {
      if (($constraint->finder == 'exact' && $classification->getLabel() == $constraint->tag ||
          $constraint->finder == 'contains' && str_contains($classification->getLabel(), $constraint->tag) ||
          $constraint->finder == 'substring' && stripos($classification->getLabel(), $constraint->tag) !== FALSE) &&
          $classification->getConfidenceScore() >= $constraint->minimum) {
        $this->context->addViolation($constraint->message, []);
      }
    }
  }

}
