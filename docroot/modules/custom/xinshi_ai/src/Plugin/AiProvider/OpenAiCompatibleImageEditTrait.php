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
use Drupal\xinshi_ai\Service\ProviderImageFiles;
use Drupal\xinshi_ai\Exception\ImageSafetyException;
use Symfony\Component\DependencyInjection\ContainerInterface;

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

  protected ProviderImageFiles $imageFiles;

  /**
   * Secure every provider call, including inherited text-to-image operations.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->setHttpClient($container->get('xinshi_ai.image_http')->create($configuration['http_client_options'] ?? []));
    $instance->imageFiles = $container->get('xinshi_ai.provider_image_files');
    return $instance;
  }

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
    $data = $response['data'] ?? [];
    if (!is_array($data) || count($data) > 16) {
      throw new ImageSafetyException('Invalid image response or too many images.');
    }
    $images = [];
    foreach ($data as $item) {
      if (!is_array($item)) {
        throw new ImageSafetyException('Invalid image response item.');
      }
      $images[] = $this->imageFiles->fromData($item);
    }
    return $images;
  }

}
