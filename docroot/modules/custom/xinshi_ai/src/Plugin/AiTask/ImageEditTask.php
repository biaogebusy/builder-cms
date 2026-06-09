<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Plugin\AiTask;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ImageToImage\ImageToImageInput;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai\AiImageTaskBase;
use Drupal\xinshi_ai\Attribute\AiTask;

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
  public function execute(NodeInterface $job): void {
    $genConfig = $this->buildGenConfig($job, ['size', 'response_format', 'quality', 'output_format']);
    $prompt = (string) $job->get('field_prompt')->value;
    $imageInput = $this->buildImageInput($job, $prompt);

    $this->runImagePipeline(
      $job,
      $genConfig,
      'image_to_image',
      fn (object $provider, string $model) => $provider->imageToImage($imageInput, $model, ['xinshi_ai']),
    );
  }

  /**
   * 从 field_input_image(media)读出源图,构造 ImageToImageInput。
   */
  private function buildImageInput(NodeInterface $job, string $prompt): ImageToImageInput {
    $media = $job->get('field_input_image')->entity;
    if (!$media) {
      throw new \RuntimeException('image_edit job 缺少 field_input_image。');
    }
    $file = $media->get('field_media_image')->entity;
    if (!$file) {
      throw new \RuntimeException('输入 media 没有可用的图片文件。');
    }
    $binary = file_get_contents($file->getFileUri());
    if ($binary === FALSE) {
      throw new \RuntimeException('读取输入图片失败:' . $file->getFileUri());
    }
    $image = new ImageFile($binary, $file->getMimeType() ?: 'image/png', $file->getFilename() ?: 'input.png');
    $input = new ImageToImageInput($image);
    if ($prompt !== '') {
      $input->setPrompt($prompt);
    }
    return $input;
  }

}
