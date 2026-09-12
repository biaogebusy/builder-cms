<?php

namespace Drupal\blazy\Media;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\FileRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides file_BLAH for D11+.
 *
 * Media component services deprecated in 3.x, and is removed in 4.x or 5.x.
 * Public access is available via @blazy.media_context coordinating layer.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module. Media integration is being reworked.
 *
 * @todo enable @trigger_error('BlazyFile is deprecated in blazy:4.0.0 and is
 * removed from blazy:5.0.0. Use @blazy.media_context instead.
 * See https://www.drupal.org/node/3575429', E_USER_DEPRECATED);
 */
class BlazyFile implements BlazyFileInterface {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The file repository service.
   *
   * @var \Drupal\file\FileRepository
   */
  protected $fileRepository;

  /**
   * The image object.
   *
   * @var \Drupal\Core\Image\ImageInterface|null
   */
  protected $image;

  /**
   * The image factory service.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * A logger instance.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a SVG manager object.
   */
  public function __construct(
    FileSystemInterface $file_system,
    FileRepository $file_repository,
    ImageFactory $image_factory,
    LoggerChannelFactoryInterface $logger,
  ) {
    $this->fileSystem = $file_system;
    $this->fileRepository = $file_repository;
    $this->imageFactory = $image_factory;
    $this->logger = $logger->get('image');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('file.repository'),
      $container->get('image.factory'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function fileSystem(): FileSystemInterface {
    return $this->fileSystem;
  }

  /**
   * {@inheritdoc}
   */
  public function fileRepository(): FileRepository {
    return $this->fileRepository;
  }

  /**
   * {@inheritdoc}
   */
  public function imageFactory(): ImageFactory {
    return $this->imageFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function image($source = NULL, $toolkit_id = NULL): ImageInterface {
    return $this->imageFactory->get($source, $toolkit_id);
  }

  /**
   * {@inheritdoc}
   */
  public function realpath($uri): string {
    return $this->fileSystem->realpath($uri);
  }

  /**
   * Alias for File::item().
   *
   * @todo deprecate and remove before or at 4.x.
   */
  public static function item($object = NULL, array $settings = [], $uri = NULL): ?object {
    return File::item($object, $settings, $uri);
  }

  /**
   * Alias for File::isValid().
   *
   * @todo deprecate and remove before or at 4.x.
   */
  public static function fromUri($uri, $manager = NULL): ?object {
    return File::fromUri($uri, $manager);
  }

  /**
   * Alias for File::isValid().
   *
   * @todo deprecate and remove before or at 4.x.
   */
  public static function isFile($file): bool {
    return File::isValid($file);
  }

  /**
   * Alias for Url::create().
   *
   * @todo deprecate and remove before or at 4.x.
   */
  public static function createUrl($uri, $relative = FALSE): string {
    return Url::create($uri, $relative);
  }

  /**
   * Alias for Url::isExternal().
   *
   * @todo deprecate and remove before or at 4.x.
   */
  public static function isExternal($uri): bool {
    return Url::isExternal($uri);
  }

  /**
   * Alias for FileImage::isSvg().
   *
   * @todo deprecate and remove before or at 4.x.
   */
  public static function isSvg($uri): bool {
    return Uri::isSvg($uri);
  }

  /**
   * Alias for FileImage::normalize().
   *
   * @todo deprecate and remove before or at 4.x.
   */
  public static function normalizeUri($path): string {
    return Uri::normalize($path);
  }

  /**
   * Alias for Uri::toAccessibleUri().
   *
   * @todo deprecate and remove before or at 4.x.
   */
  public static function toAccessibleUri($uri): string {
    return Uri::toAccessibleUri($uri);
  }

  /**
   * Alias for Uri::fromImage().
   *
   * @todo deprecate and remove before or at 4.x.
   */
  public static function uri($item, array $settings = []): ?string {
    return Uri::fromImage($item, $settings);
  }

  /**
   * Alias for Uri::transformRelative().
   *
   * @todo deprecate and remove before or at 4.x.
   */
  public static function transformRelative($uri, $style = NULL, array $options = []): string {
    return Uri::transformRelative($uri, $style, $options);
  }

  /**
   * Alias for FileImage::isValid().
   *
   * @todo deprecate and remove before or at 4.x.
   */
  public static function isValidUri($uri): bool {
    return Uri::isValid($uri);
  }

  /**
   * Alias for Uri::build().
   *
   * @todo deprecate and remove before or at 4.x.
   */
  public static function buildUri($url): ?string {
    return Uri::build($url);
  }

}
