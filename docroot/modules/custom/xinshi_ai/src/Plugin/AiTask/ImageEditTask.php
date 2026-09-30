<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Plugin\AiTask;

use Drupal\ai\OperationType\ImageToImage\ImageToImageInput;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai\AiImageTaskBase;
use Drupal\xinshi_ai\Attribute\AiTask;
use Drupal\xinshi_ai\Service\ImageJobRun;
use Drupal\xinshi_ai\Service\InputImageAccess;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * 图生图 / 图片编辑任务:image(+prompt) → image,经 Provider 调网关 /v1/images/edits。
 */
#[AiTask(
  id: 'image_edit',
  label: 'Image Edit (image → image)',
  jobBundle: 'image_job',
  jobKind: 'image_edit',
  defaultN: 1,
  platforms: ['xinshi', 'custom'],
)]
final class ImageEditTask extends AiImageTaskBase {

  protected InputImageAccess $inputImages;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->inputImages = $container->get('xinshi_ai.input_images');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $input, AccountInterface $account): array {
    $errors = $this->validateModelCapability($input, 'image-edit');
    if (empty($input['inputImage'])) {
      $errors['inputImage'] = 'Input image is required.';
    }
    if (empty($input['prompt'])) {
      $errors['prompt'] = 'Prompt is required.';
    }
    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(NodeInterface $job, ?ImageJobRun $run = NULL): void {
    $genConfig = $this->buildGenConfig($job, ['size', 'response_format', 'quality', 'output_format']);
    $prompt = (string) $job->get('field_prompt')->value;
    $this->runImagePipeline(
      $job,
      $genConfig,
      'image_to_image',
      fn (object $provider, string $model) => $provider->imageToImage($this->buildImageInput($job, $prompt), $model, ['xinshi_ai']),
      $run,
    );
  }

  /**
   * 从 field_input_image(media)读出源图,构造 ImageToImageInput。
   */
  private function buildImageInput(NodeInterface $job, string $prompt): ImageToImageInput {
    $image = $this->inputImages->forJob($job);
    $input = new ImageToImageInput($image);
    if ($prompt !== '') {
      $input->setPrompt($prompt);
    }
    return $input;
  }

}
