<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Plugin\AiProvider;

use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ImageToImage\ImageToImageInput;
use Drupal\ai\OperationType\ImageToImage\ImageToImageOutput;
use Drupal\ai\Traits\OperationType\ImageToImageTrait;

/**
 * 给 OpenAI-compatible 网关补上 image_to_image(图生图 / 编辑)。
 *
 * ai_provider_openai 的 OpenAiProvider 不实现 ImageToImageInterface,这里用
 * openai-php 的 images()->edit()(multipart /v1/images/edits)补齐:把输入图
 * 写到临时 .png 再以文件句柄塞进 multipart 的 image 字段,响应解析复用
 * textToImage 的 data[].url|b64_json 逻辑。
 *
 * 使用方需 extends OpenAiProvider(以拿到 $this->client / $this->configuration /
 * loadClient() / moderationEndpoints())并在 getSupportedOperationTypes() 里
 * 追加 'image_to_image'。
 */
trait OpenAiCompatibleImageEditTrait {

  use ImageToImageTrait;

  /**
   * {@inheritdoc}
   */
  public function imageToImage(string|array|ImageToImageInput $input, string $model_id, array $tags = []): ImageToImageOutput {
    $this->loadClient();

    $imageFile = $this->resolveInputImage($input);
    $prompt = $input instanceof ImageToImageInput ? $input->getPrompt() : NULL;

    // 编辑指令也过一遍内容审核(与 textToImage 一致)。
    if ($prompt !== NULL && $prompt !== '') {
      $this->moderationEndpoints($prompt);
    }

    $tmpImage = $this->writeTempImage($imageFile);
    $imageHandle = fopen($tmpImage, 'r');
    $maskHandle = NULL;
    $tmpMask = NULL;

    try {
      $payload = ['model' => $model_id, 'image' => $imageHandle] + $this->configuration;
      if ($prompt !== NULL && $prompt !== '') {
        $payload['prompt'] = $prompt;
      }
      if ($input instanceof ImageToImageInput && $input->getMask() !== NULL) {
        $tmpMask = $this->writeTempImage($input->getMask());
        $maskHandle = fopen($tmpMask, 'r');
        $payload['mask'] = $maskHandle;
      }

      try {
        $response = $this->client->images()->edit($payload)->toArray();
      }
      catch (\Exception $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, 'Request too large') || str_contains($msg, 'Too Many Requests')) {
          throw new AiRateLimitException($msg);
        }
        if (str_contains($msg, 'You exceeded your current quota')) {
          throw new AiQuotaException($msg);
        }
        throw $e;
      }

      $images = $this->parseImageResponse($response, $model_id);
      if (empty($images)) {
        throw new AiResponseErrorException('Failed to process any valid images from the edit response.');
      }
      return new ImageToImageOutput($images, $response, []);
    }
    finally {
      if (is_resource($imageHandle)) {
        fclose($imageHandle);
      }
      if (is_resource($maskHandle)) {
        fclose($maskHandle);
      }
      @unlink($tmpImage);
      if ($tmpMask !== NULL) {
        @unlink($tmpMask);
      }
    }
  }

  /**
   * 归一化 image_to_image 输入到 ImageFile。
   */
  private function resolveInputImage(string|array|ImageToImageInput $input): ImageFile {
    if ($input instanceof ImageToImageInput) {
      return $input->getImageFile();
    }
    if (is_string($input)) {
      return new ImageFile($input, 'image/png', 'input.png');
    }
    // array:[binary, mime, filename]。
    return new ImageFile(
      (string) ($input[0] ?? ''),
      (string) ($input[1] ?? 'image/png'),
      (string) ($input[2] ?? 'input.png'),
    );
  }

  /**
   * 把 ImageFile 写到临时文件,返回路径(multipart 需要真实文件句柄取文件名)。
   */
  private function writeTempImage(ImageFile $file): string {
    $ext = match ($file->getMimeType()) {
      'image/jpeg', 'image/jpg' => 'jpg',
      'image/webp' => 'webp',
      default => 'png',
    };
    $path = sys_get_temp_dir() . '/xinshi_ai_edit_' . bin2hex(random_bytes(8)) . '.' . $ext;
    file_put_contents($path, $file->getBinary());
    return $path;
  }

  /**
   * 解析 images 响应的 data[](b64_json / url)→ ImageFile[]。
   */
  private function parseImageResponse(array $response, string $model_id): array {
    $images = [];
    foreach ($response['data'] ?? [] as $data) {
      $isGptImage = str_starts_with($model_id, 'gpt-image') || isset($data['revised_prompt']);
      $name = ($isGptImage ? 'gpt-image' : 'dalle');
      if (isset($data['b64_json'])) {
        [$mime, $ext] = $this->mimeFromFormat($response['output_format'] ?? ($this->configuration['output_format'] ?? NULL));
        $images[] = new ImageFile(base64_decode($data['b64_json']), $mime, $name . '.' . $ext);
      }
      elseif (!empty($data['url'])) {
        $content = @file_get_contents($data['url']);
        if ($content !== FALSE) {
          $images[] = new ImageFile($content, 'image/png', $name . '.png');
        }
        else {
          $this->logger->error('Failed to fetch edited image from URL: @url', ['@url' => $data['url']]);
        }
      }
    }
    return $images;
  }

  /**
   * output_format → [mime, ext]。
   */
  private function mimeFromFormat(?string $format): array {
    return match ($format) {
      'jpeg' => ['image/jpeg', 'jpeg'],
      'webp' => ['image/webp', 'webp'],
      default => ['image/png', 'png'],
    };
  }

}
