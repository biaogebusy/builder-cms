<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Normalizer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

/**
 * 在 json 序列化(/api/v2/image-jobs)里把 image_job 的 field_assets
 * 从实体引用换成 inline 资产对象,使列表单次请求即带图片 URL。
 *
 * 仅作用于 format=json,不触碰 JSON:API(api_json),故单资源
 * /api/v1/node/image_job/:uuid 与 SSE/删除/标星路径不受影响。
 */
final class ImageJobNormalizer implements NormalizerInterface, SerializerAwareInterface {

  use SerializerAwareTrait;

  /** 委托默认实体序列化时设此标志,避免递归命中自身。 */
  private const DELEGATE_FLAG = 'xinshi_ai_image_job_delegate';

  /** 缩略图样式(方形,匹配前端资产方格);缺失回退原图。 */
  private const THUMB_STYLE = 'media_1_1';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function normalize($object, ?string $format = NULL, array $context = []): array {
    $context[self::DELEGATE_FLAG] = TRUE;
    $data = $this->serializer->normalize($object, $format, $context);
    assert($object instanceof NodeInterface);
    $data['field_assets'] = $this->inlineAssets($object);
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, ?string $format = NULL, array $context = []): bool {
    return empty($context[self::DELEGATE_FLAG])
      && $format === 'json'
      && $data instanceof NodeInterface
      && $data->bundle() === 'image_job';
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [NodeInterface::class => FALSE];
  }

  /**
   * 把 field_assets 引用的每个 image_asset 节点序列化为 inline 对象。
   */
  private function inlineAssets(NodeInterface $job): array {
    $assets = [];
    foreach ($job->get('field_assets')->referencedEntities() as $asset) {
      if (!$asset instanceof NodeInterface) {
        continue;
      }
      $assets[] = $this->serializeAsset($asset);
    }
    return $assets;
  }

  /**
   * 单个 image_asset 的 inline 形状(对齐 SSE asset_ready 的扩展版)。
   */
  private function serializeAsset(NodeInterface $asset): array {
    $media = $asset->get('field_asset_image')->entity;
    $file = $media instanceof MediaInterface ? $media->get('field_media_image')->entity : NULL;

    $src = NULL;
    $thumb = NULL;
    if ($file) {
      // 根相对路径(TRUE),避免 CLI 下回退 http://default。
      $src = $file->createFileUrl(TRUE);
      $uri = $file->getFileUri();
      $style = ImageStyle::load(self::THUMB_STYLE);
      // buildUrl 默认返回绝对 URL,但在 CLI 下同样回退 http://default,
      // 需手动转相对:去掉 scheme+host,保留 /sites/...
      if ($style) {
        $absolute = $style->buildUrl($uri);
        $thumb = preg_replace('#^https?://[^/]+#', '', $absolute);
      }
      else {
        $thumb = $src;
      }
    }

    $width = $asset->get('field_width')->value;
    $height = $asset->get('field_height')->value;
    $meta = json_decode((string) ($asset->get('field_meta')->value ?? ''), TRUE) ?: NULL;

    return [
      'uuid' => $asset->uuid(),
      'index' => (int) $asset->get('field_index')->value,
      'status' => $asset->get('field_status')->value,
      'seed' => (string) ($asset->get('field_seed')->value ?? ''),
      'starred' => (bool) $asset->get('field_starred')->value,
      'width' => $width !== NULL ? (int) $width : NULL,
      'height' => $height !== NULL ? (int) $height : NULL,
      'mediaUuid' => $media instanceof MediaInterface ? $media->uuid() : NULL,
      'fileUuid' => $file ? $file->uuid() : NULL,
      'src' => $src,
      'thumb' => $thumb ?? $src,
      'meta' => $meta,
    ];
  }

}
