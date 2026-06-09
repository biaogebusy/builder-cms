<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Plugin\AiTask;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai\AiImageTaskBase;
use Drupal\xinshi_ai\Attribute\AiTask;

/**
 * 文生图任务:text → image,经 drupal/ai Provider 调网关,落 media:image。
 */
#[AiTask(
  id: 'image_generation',
  label: 'Image Generation (text → image)',
  jobBundle: 'image_job',
  jobKind: 'text_to_image',
  defaultN: 4,
  platforms: ['xinshi', 'custom'],
)]
final class ImageGenerationTask extends AiImageTaskBase {

  /**
   * {@inheritdoc}
   */
  public function validate(array $input, AccountInterface $account): array {
    $errors = $this->validateModelCapability($input, 'image');
    if (empty($input['prompt'])) {
      $errors['prompt'] = 'Prompt is required.';
    }
    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(NodeInterface $job): void {
    $genConfig = $this->buildGenConfig($job, ['size', 'response_format', 'quality', 'output_format']);
    $prompt = (string) $job->get('field_prompt')->value;

    $this->runImagePipeline(
      $job,
      $genConfig,
      'text_to_image',
      fn (object $provider, string $model) => $provider->textToImage($prompt, $model, ['xinshi_ai']),
    );
  }

}
